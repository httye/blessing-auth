<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Announcement;
use App\Models\Option;
use App\Models\OAuthClient;
use App\Models\Player;
use App\Models\Texture;
use App\Models\Title;
use App\Models\User;
use App\Plugins\Hook;
use App\Plugins\PluginManager;
use App\Services\OAuth\ProviderRegistry;
use App\Services\BackupService;
use App\Services\AuditService;
use App\Services\ScoreService;
use App\Services\TextureStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private readonly TextureStorage $storage,
        private readonly AuditService $audit,
    ) {
    }

    public function index()
    {
        return view('admin.index', [
            'stats' => [
                'users' => User::count(),
                'players' => Player::count(),
                'textures' => Texture::count(),
            ],
        ]);
    }

    public function settings()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403, '站点设置仅超级管理员可用。');

        return view('admin.settings', [
            'options' => [
                'site_name' => option('site_name', config('app.name')),
                'site_description' => option('site_description', ''),
                'allow_register' => option_bool('allow_register'),
                'email_verification' => option_bool('email_verification'),
                'email_domain_mode' => (string) option('email_domain_mode', 'off'),
                'email_domains' => (string) option('email_domains', ''),
                'initial_score' => option_int('initial_score'),
                'player_cost' => option_int('player_cost'),
                'sign_score' => option_int('sign_score'),
                'token_valid_hours' => option_int('token_valid_hours'),
                'token_expire_hours' => option_int('token_expire_hours'),
                'tokens_limit' => option_int('tokens_limit'),
                'max_textures' => option_int('max_textures', 100),
                'site_language' => option('site_language', 'zh_CN'),
                'texture_storage_driver' => option('texture_storage_driver', 'local'),
                'skin_domains' => (string) option('skin_domains', ''),
            ],
            'oauthProviders' => ProviderRegistry::all(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, '站点设置仅超级管理员可用。');

        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'site_description' => ['nullable', 'string', 'max:255'],
            'allow_register' => ['nullable', 'boolean'],
            'email_verification' => ['nullable', 'boolean'],
            'email_domain_mode' => ['required', 'in:off,whitelist,blacklist'],
            'email_domains' => ['nullable', 'string', 'max:1000'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_encryption' => ['nullable', 'in:ssl,tls,none'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from' => ['nullable', 'email', 'max:255'],
            'initial_score' => ['required', 'integer', 'min:0', 'max:1000000'],
            'player_cost' => ['required', 'integer', 'min:0', 'max:1000000'],
            'sign_score' => ['required', 'integer', 'min:0', 'max:100000'],
            'token_valid_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'token_expire_hours' => ['required', 'integer', 'min:1', 'max:8760', 'gte:token_valid_hours'],
            'tokens_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'max_textures' => ['required', 'integer', 'min:0', 'max:10000'],
            'skin_domains' => ['nullable', 'string', 'max:500'],
            'site_language' => ['required', 'in:zh_CN,en'],
            'texture_storage_driver' => ['required', 'in:local,s3,webdav'],
        ], [
            'token_expire_hours.gte' => '彻底失效期必须不小于有效期。',
        ]);

        Option::set('site_name', $data['site_name']);
        Option::set('site_description', $data['site_description'] ?? '');
        Option::set('allow_register', $request->boolean('allow_register') ? '1' : '0');
        Option::set('email_verification', $request->boolean('email_verification') ? '1' : '0');
        Option::set('email_domain_mode', $data['email_domain_mode']);
        Option::set('email_domains', trim($data['email_domains'] ?? ''));

        // SMTP（密码留空不修改）
        Option::set('mail.host', trim($data['mail_host'] ?? ''));
        Option::set('mail.port', $data['mail_port'] ?? 465);
        Option::set('mail.encryption', $data['mail_encryption'] ?? 'ssl');
        Option::set('mail.username', trim($data['mail_username'] ?? ''));
        Option::set('mail.from', trim($data['mail_from'] ?? ''));
        if (! empty($data['mail_password'])) {
            Option::set('mail.password', $data['mail_password']);
        }
        Option::set('initial_score', $data['initial_score']);
        Option::set('player_cost', $data['player_cost']);
        Option::set('sign_score', $data['sign_score']);
        Option::set('token_valid_hours', $data['token_valid_hours']);
        Option::set('token_expire_hours', $data['token_expire_hours']);
        Option::set('tokens_limit', $data['tokens_limit']);
        Option::set('skin_domains', trim($data['skin_domains'] ?? ''));
        Option::set('max_textures', $data['max_textures']);
        Option::set('site_language', $data['site_language']);
        Option::set('texture_storage_driver', $data['texture_storage_driver']);

        // OAuth 提供商配置
        foreach (ProviderRegistry::all() as $p) {
            $prefix = "oauth_{$p->name}";
            Option::set("oauth.{$p->name}.enabled", $request->boolean("{$prefix}_enabled") ? '1' : '0');
            Option::set("oauth.{$p->name}.client_id", trim((string) $request->input("{$prefix}_client_id", '')));

            // Secret 留空表示不修改（避免每次保存都要重填）
            $secret = trim((string) $request->input("{$prefix}_client_secret", ''));
            if ($secret !== '') {
                Option::set("oauth.{$p->name}.client_secret", $secret);
            }
        }

        return back()->with('success', '站点设置已保存。');
    }

    public function plugins(PluginManager $plugins)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403, '插件管理仅超级管理员可用。');

        return view('admin.plugins', ['plugins' => $plugins->all()]);
    }

    public function togglePlugin(Request $request, PluginManager $plugins): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, '插件管理仅超级管理员可用。');

        $data = $request->validate([
            'name' => ['required', 'string'],
            'action' => ['required', 'in:enable,disable'],
        ]);

        try {
            $ok = $data['action'] === 'enable'
                ? $plugins->enable($data['name'])
                : $plugins->disable($data['name']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (! $ok) {
            return back()->with('error', '插件不存在。');
        }

        return back()->with('success', $data['action'] === 'enable' ? '插件已启用。' : '插件已停用。');
    }

    public function announcements()
    {
        return view('admin.announcements', [
            'announcements' => Announcement::query()
                ->with('user')
                ->orderByDesc('pinned')
                ->orderByDesc('created_at')
                ->paginate(15),
        ]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string', 'max:20000'],
            'pinned' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
        ]);

        Announcement::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'author' => $request->user()->id,
            'pinned' => $request->boolean('pinned'),
            'published' => $request->boolean('published', true),
        ]);

        return back()->with('success', '公告已发布。');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string', 'max:20000'],
            'pinned' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
        ]);

        $announcement->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'pinned' => $request->boolean('pinned'),
            'published' => $request->boolean('published'),
        ]);

        return back()->with('success', '公告已更新。');
    }

    public function destroyAnnouncement(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', '公告已删除。');
    }

    public function backups(BackupService $backup)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403, '备份管理仅超级管理员可用。');

        return view('admin.backups', ['backups' => $backup->list()]);
    }

    public function createBackup(BackupService $backup): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        try {
            $result = $backup->create();
        } catch (\Throwable $e) {
            return back()->with('error', '备份失败：'.$e->getMessage());
        }

        return back()->with('success', '备份完成：'.basename($result['file']));
    }

    public function downloadBackup(string $name, BackupService $backup)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $path = $backup->resolve($name);
        abort_if($path === null, 404);

        return response()->download($path);
    }

    public function destroyBackup(string $name, BackupService $backup): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        return $backup->delete($name)
            ? back()->with('success', '备份已删除。')
            : back()->with('error', '备份不存在。');
    }

    public function titles()
    {
        return view('admin.titles', [
            'titles' => Title::query()->withCount('holders')->orderBy('id')->get(),
        ]);
    }

    public function storeTitle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'purchasable' => ['nullable', 'boolean'],
        ]);

        Title::create([
            'name' => $data['name'],
            'color' => strtolower($data['color']),
            'price' => $data['price'],
            'purchasable' => $request->boolean('purchasable'),
        ]);

        return back()->with('success', '称号已创建。');
    }

    public function destroyTitle(Title $title): RedirectResponse
    {
        $title->delete(); // user_titles 级联删除，current_title_id 置空

        return back()->with('success', '称号已删除。');
    }

    /** 授予称号 */
    public function grantTitle(Request $request, Title $title): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ], [
            'email.exists' => '该邮箱对应的用户不存在。',
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user->titles()->where('title_id', $title->id)->exists()) {
            return back()->with('error', '该用户已拥有此称号。');
        }

        $user->titles()->attach($title->id, ['acquired_at' => now()]);
        Hook::fire('title.acquired', $user, $title);

        return back()->with('success', "已授予 {$user->nickname}「{$title->name}」称号。");
    }

    public function oauthClients()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403, '接入应用管理仅超级管理员可用。');

        return view('admin.oauth-clients', [
            'clients' => OAuthClient::query()->latest()->get(),
        ]);
    }

    public function storeOAuthClient(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'redirect_uri' => ['required', 'string', 'max:2000'],
        ]);

        $client = OAuthClient::generate($data['name'], $data['redirect_uri']);

        return back()->with('success', "应用已创建。client_id: {$client->client_id}，client_secret 请在列表中查看并妥善保管。");
    }

    public function toggleOAuthClient(OAuthClient $client): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $client->update(['enabled' => ! $client->enabled]);

        Auth::forgetInstance();

        return back()->with('success', $client->enabled ? '应用已启用。' : '应用已停用（其令牌立即失效）。');
    }

    public function destroyOAuthClient(OAuthClient $client): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $client->delete(); // 级联删除令牌和授权记录

        return back()->with('success', '应用已删除。');
    }

    public function auditLogs()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        return view('admin.audit', [
            'logs' => AdminAuditLog::query()->with(['operator', 'targetUser'])
                ->latest('created_at')
                ->paginate(50),
        ]);
    }

    public function users(Request $request)
    {
        $query = User::query()->withCount('players')->latest();

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(fn ($w) => $w
                ->where('email', 'like', "%{$q}%")
                ->orWhere('nickname', 'like', "%{$q}%"));
        }

        return view('admin.users', ['users' => $query->paginate(20)->withQueryString()]);
    }

    public function updatePermission(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'permission' => ['required', 'integer', 'in:-1,0,1,2'],
            'ban_reason' => ['nullable', 'string', 'max:255'],
            'ban_days' => ['nullable', 'integer', 'min:0', 'max:36500'], // 0/空 = 永久
        ]);

        $operator = $request->user();
        $target = (int) $data['permission'];

        if ($user->id === $operator->id) {
            return back()->with('error', '不能修改自己的权限。');
        }

        // 权限层级：不能操作权限 >= 自己的用户；不能授予 >= 自己的权限
        if ($user->permission >= $operator->permission) {
            return back()->with('error', '不能操作权限不低于你的用户。');
        }

        if ($target >= $operator->permission) {
            return back()->with('error', '不能授予不低于你自身的权限。');
        }

        if ($target === User::PERMISSION_BANNED) {
            $days = (int) ($data['ban_days'] ?? 0);
            $user->update([
                'permission' => User::PERMISSION_BANNED,
                'ban_reason' => $data['ban_reason'] ?? null,
                'ban_until' => $days > 0 ? now()->addDays($days) : null,
            ]);
            $user->yggTokens()->delete();
            Hook::fire('user.banned', $user, $operator);
            $this->audit->logWithTarget(AuditService::ACTION_BAN_USER, $user,
                ($days > 0 ? "封禁 {$days} 天" : '永久封禁').($data['ban_reason'] ? '，原因：'.$data['ban_reason'] : ''));

            return back()->with('success', '用户已封禁'.($days > 0 ? "（{$days} 天）" : '（永久）').'。');
        }

        $wasBanned = $user->permission <= User::PERMISSION_BANNED;

        $user->update([
            'permission' => $target,
            'ban_reason' => null,
            'ban_until' => null,
        ]);

        if ($wasBanned) {
            Hook::fire('user.unbanned', $user, $operator);
            $this->audit->logWithTarget(AuditService::ACTION_UNBAN_USER, $user, '解封');
        } else {
            $this->audit->logWithTarget(AuditService::ACTION_MODIFY_PERMISSION, $user,
                '权限从 '.$user->permission.' 变更为 '.$target);
        }

        return back()->with('success', '权限已更新。');
    }

    public function updateScore(Request $request, User $user, ScoreService $score): RedirectResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $score->setBalance($user, $data['score'], '管理员调整（操作者 #'.$request->user()->id.'）');
        $this->audit->logWithTarget(AuditService::ACTION_MODIFY_SCORE, $user,
            '积分设置为 '.$data['score'].'（原 '.$user->score.'）');

        return back()->with('success', '积分已更新。');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if ($user->id !== $request->user()->id && $user->permission >= $request->user()->permission) {
            return back()->with('error', '不能操作权限不低于你的用户。');
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        $user->update(['password' => $data['password']]);
        $user->yggTokens()->delete();

        Hook::fire('user.password.changed', $user);
        $this->audit->logWithTarget(AuditService::ACTION_RESET_PASSWORD, $user, '管理员重置密码');

        return back()->with('success', '密码已重置，该用户所有令牌已失效。');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', '不能删除自己。');
        }

        if ($user->permission >= $request->user()->permission) {
            return back()->with('error', '不能删除权限不低于你的用户。');
        }

        $userSummary = "#{$user->id} {$user->email}";
        $user->delete(); // 级联删除角色和令牌
        $this->audit->log(AuditService::ACTION_DELETE_USER, null, '删除用户 '.$userSummary);

        return back()->with('success', '用户已删除。');
    }

    public function players(Request $request)
    {
        $query = Player::query()->with('user')->latest();

        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->query('q').'%');
        }

        return view('admin.players', ['players' => $query->paginate(20)->withQueryString()]);
    }

    public function destroyPlayer(Player $player): RedirectResponse
    {
        $player->delete();
        $this->audit->logWithTarget(AuditService::ACTION_DELETE_PLAYER, $player->user ?? null,
            '删除角色 #'.$player->id.'（'.$player->name.'）');

        return back()->with('success', '角色已删除。');
    }

    public function textures(Request $request)
    {
        $query = Texture::query()->with('owner')->latest();

        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->query('q').'%');
        }

        return view('admin.textures', ['textures' => $query->paginate(20)->withQueryString()]);
    }

    public function destroyTexture(Texture $texture): RedirectResponse
    {
        $hash = $texture->hash;
        $texture->delete();
        $this->storage->deleteIfUnused($hash);
        $this->audit->logWithTarget(AuditService::ACTION_DELETE_TEXTURE, $texture->owner,
            '删除材质 #'.$texture->id.'（'.$texture->name.'，'.$texture->type.'）');

        return back()->with('success', '材质已删除。');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function index()
    {
        return view('announcement.index', [
            'announcements' => Announcement::query()->visible()->with('user')->paginate(10),
        ]);
    }

    public function show(Announcement $announcement)
    {
        abort_unless($announcement->published, 404);

        return view('announcement.show', ['announcement' => $announcement]);
    }
}

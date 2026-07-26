@if ($paginator->hasPages())
    <nav style="display:flex;justify-content:center;gap:.5rem;margin-top:1rem">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true">‹ 上一页</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}">‹ 上一页</a>
        @endif

        <span>第 {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }} 页</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}">下一页 ›</a>
        @else
            <span aria-disabled="true">下一页 ›</span>
        @endif
    </nav>
@endif

<div class="table-footer">
    <p>{{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} dari {{ number_format($records->total(), 0, ',', '.') }} data</p>
    <nav class="pagination" aria-label="Halaman data">
        @if($records->onFirstPage())<span aria-disabled="true">← Sebelumnya</span>@else<a rel="prev" href="{{ $records->previousPageUrl() }}">← Sebelumnya</a>@endif
        <span class="page-number">{{ $records->currentPage() }} / {{ $records->lastPage() }}</span>
        @if($records->hasMorePages())<a rel="next" href="{{ $records->nextPageUrl() }}">Berikutnya →</a>@else<span aria-disabled="true">Berikutnya →</span>@endif
    </nav>
</div>

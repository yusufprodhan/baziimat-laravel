@extends('master.back')

@section('content')

<div class="container-fluid">

    <!-- Page Heading -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0 bc-title"><b>{{ __('Log Viewer') }}</b></h3>
            </div>
        </div>
    </div>

    <div class="row">

        <!-- Log Files List -->
        <div class="col-xl-3 col-lg-4 col-md-12 mb-4">
            <div class="card o-hidden border-0 shadow-lg">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-folder-open"></i> {{ __('Log Files') }}</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($logFiles as $file)
                            <a href="{{ route('back.log.index', ['file' => $file['name']]) }}"
                               class="list-group-item list-group-item-action {{ $selectedFile == $file['name'] ? 'active' : '' }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-file-alt mr-1"></i>
                                        <small><b>{{ $file['name'] }}</b></small>
                                    </div>
                                </div>
                                <small class="{{ $selectedFile == $file['name'] ? 'text-white-50' : 'text-muted' }}">
                                    {{ $file['size'] }} | {{ $file['last_modified'] }}
                                </small>
                            </a>
                        @empty
                            <div class="list-group-item text-center text-muted">
                                {{ __('No log files found.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Log Content -->
        <div class="col-xl-9 col-lg-8 col-md-12">
            <div class="card o-hidden border-0 shadow-lg">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list"></i> {{ $selectedFile }}
                        @if(count($logEntries) > 0)
                            <span class="badge badge-light ml-2">{{ count($logEntries) }} {{ __('entries') }}</span>
                        @endif
                    </h6>
                    <div>
                        <!-- Download -->
                        <a href="{{ route('back.log.download', ['file' => $selectedFile]) }}"
                           class="btn btn-sm btn-info" title="{{ __('Download') }}">
                            <i class="fas fa-download"></i>
                        </a>

                        <!-- Clear Log -->
                        <form action="{{ route('back.log.clear') }}" method="POST" class="d-inline"
                              onsubmit="return confirm('{{ __('Are you sure you want to clear this log file?') }}');">
                            @csrf
                            <input type="hidden" name="file" value="{{ $selectedFile }}">
                            <button type="submit" class="btn btn-sm btn-warning" title="{{ __('Clear Log') }}">
                                <i class="fas fa-eraser"></i>
                            </button>
                        </form>

                        <!-- Delete Log -->
                        @if($selectedFile !== 'laravel.log')
                        <form action="{{ route('back.log.delete') }}" method="POST" class="d-inline"
                              onsubmit="return confirm('{{ __('Are you sure you want to delete this log file?') }}');">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="file" value="{{ $selectedFile }}">
                            <button type="submit" class="btn btn-sm btn-danger" title="{{ __('Delete File') }}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
                <div class="card-body p-3">

                    @include('alerts.alerts')

                    <!-- Filter -->
                    <div class="mb-3">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary log-filter active" data-level="all">
                                {{ __('All') }}
                            </button>
                            <button type="button" class="btn btn-outline-danger log-filter" data-level="error">
                                {{ __('Error') }}
                            </button>
                            <button type="button" class="btn btn-outline-warning log-filter" data-level="warning">
                                {{ __('Warning') }}
                            </button>
                            <button type="button" class="btn btn-outline-info log-filter" data-level="info">
                                {{ __('Info') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary log-filter" data-level="debug">
                                {{ __('Debug') }}
                            </button>
                        </div>
                    </div>

                    <!-- Log Entries -->
                    <div class="log-entries" style="max-height: 700px; overflow-y: auto;">
                        @forelse($logEntries as $index => $entry)
                            @php
                                $levelClass = 'secondary';
                                if ($entry['level'] == 'error' || $entry['level'] == 'critical' || $entry['level'] == 'emergency' || $entry['level'] == 'alert') {
                                    $levelClass = 'danger';
                                } elseif ($entry['level'] == 'warning') {
                                    $levelClass = 'warning';
                                } elseif ($entry['level'] == 'info' || $entry['level'] == 'notice') {
                                    $levelClass = 'info';
                                } elseif ($entry['level'] == 'debug') {
                                    $levelClass = 'secondary';
                                }
                            @endphp
                            <div class="log-entry border-left border-{{ $levelClass }} mb-2 p-2"
                                 data-level="{{ $entry['level'] }}"
                                 style="border-left-width: 4px !important; background: #f8f9fa; font-size: 13px;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge badge-{{ $levelClass }}">{{ strtoupper($entry['level']) }}</span>
                                        <small class="text-muted ml-2">{{ $entry['timestamp'] }}</small>
                                        <small class="text-muted ml-1">[{{ $entry['environment'] }}]</small>
                                    </div>
                                    @if($entry['stack'])
                                        <button class="btn btn-sm btn-outline-secondary py-0 px-1 toggle-stack"
                                                data-target="stack-{{ $index }}">
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                    @endif
                                </div>
                                <div class="mt-1" style="word-break: break-word;">
                                    {{ \Illuminate\Support\Str::limit($entry['message'], 500) }}
                                </div>
                                @if($entry['stack'])
                                    <div id="stack-{{ $index }}" class="mt-2 d-none">
                                        <pre style="font-size: 11px; max-height: 300px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; padding: 10px; border-radius: 4px; white-space: pre-wrap; word-break: break-all;">{{ trim($entry['stack']) }}</pre>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <p>{{ __('No log entries found.') }}</p>
                            </div>
                        @endforelse
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

@endsection

@section('scripts')
<script>
    // Toggle stack trace
    $(document).on('click', '.toggle-stack', function() {
        var target = $(this).data('target');
        $('#' + target).toggleClass('d-none');
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    // Filter log entries by level
    $(document).on('click', '.log-filter', function() {
        $('.log-filter').removeClass('active');
        $(this).addClass('active');
        var level = $(this).data('level');

        if (level === 'all') {
            $('.log-entry').show();
        } else {
            $('.log-entry').hide();
            if (level === 'error') {
                $('.log-entry[data-level="error"], .log-entry[data-level="critical"], .log-entry[data-level="emergency"], .log-entry[data-level="alert"]').show();
            } else {
                $('.log-entry[data-level="' + level + '"]').show();
            }
        }
    });
</script>
@endsection

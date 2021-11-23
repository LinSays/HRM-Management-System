@extends('layouts.app')

@section('page-title')
    <div class="row bg-title">
        <!-- .page title -->
        <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12 bg-title-left">
            <h4 class="page-title"><i class="{{ $pageIcon }}"></i> {{ __($pageTitle) }}</h4>
        </div>
        <div class="col-sm-9 text-right">
            <div class="form-group">
                <span  id="export_csv" class="btn btn-success btn-sm" onclick="exportCsv(event.target);">Export CSV</span>
                <span  id="export_pdf" class="btn btn-success btn-sm" onclick="exportPdf(event.target);">Export PDF</span>
                <span  id="print" class="btn btn-success btn-sm" onclick="print_member(event.target);">Print</span>
                <a href="{{ route('admin.attendances.create') }}"
                   class="btn btn-success btn-sm">@lang('modules.attendance.markAttendance') <i class="fa fa-plus"
                                                                                                aria-hidden="true"></i></a>
            </div>
        </div>
        <!-- /.page title -->
        <!-- .breadcrumb -->
        <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12 bg-title-right">
            <ol class="breadcrumb">
                <li><a href="{{ route('admin.dashboard') }}">@lang('app.menu.home')</a></li>
                <li class="active">{{ __($pageTitle) }}</li>
            </ol>
        </div>
        <!-- /.breadcrumb -->
    </div>
@endsection

@push('head-script')
<link rel="stylesheet" href="{{ asset('plugins/bower_components/morrisjs/morris.css') }}">

<link rel="stylesheet" href="{{ asset('plugins/bower_components/custom-select/custom-select.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/bower_components/bootstrap-select/bootstrap-select.min.css') }}">

<link rel="stylesheet" href="https://cdn.datatables.net/1.10.13/css/dataTables.bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.1.1/css/responsive.bootstrap.min.css">
<link rel="stylesheet" href="//cdn.datatables.net/buttons/1.2.2/css/buttons.dataTables.min.css">
<link rel="stylesheet" type="text/css" href="{{ asset('plugins/daterange-picker/daterangepicker.css') }}" />
<style>
    .al-center{
        text-align:center;
    }
    .bt-border {
        border-bottom: 1px #cccccc solid;
    }
</style>
@endpush

@section('content')
    <div class="row">


        <div class="sttabs tabs-style-line col-md-12">
            <div class="white-box">
                <nav>
                    <ul>
                        <li><a href="{{ route('admin.attendances.summary') }}"><span>@lang('app.summary')</span></a>

                        <li class="tab-current"><a href="{{ route('admin.attendances.index') }}"><span>@lang('modules.attendance.attendanceByMember')</span></a>
                        </li>
                        <li><a href="{{ route('admin.attendances.attendanceByDate') }}"><span>@lang('modules.attendance.attendanceByDate')</span></a>
                        </li>

                    </ul>
                </nav>
            </div>
        </div>
    </div>
    <!-- .row -->

    <div class="row">
        <div class="col-xs-12">
            <div class="white-box p-b-0">
                <div class="row">
                    <div class="col-md-4">
                        <label class="control-label">@lang('app.selectDateRange')</label>

                        <div class="form-group">
                            <div id="reportrange" class="form-control reportrange">
                                <i class="fa fa-calendar"></i>&nbsp;
                                <span></span> <i class="fa fa-caret-down pull-right"></i>
                            </div>

                            <input type="hidden" class="form-control" id="start-date" placeholder="@lang('app.startDate')"
                                   value="{{ $startDate->format($global->date_format) }}"/>
                            <input type="hidden" class="form-control" id="end-date" placeholder="@lang('app.endDate')"
                                   value="{{ $endDate->format($global->date_format) }}"/>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">@lang('modules.timeLogs.employeeName')</label>
                            <select class="select2 form-control" data-placeholder="Choose Employee" id="user_id" name="user_id">
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ ucwords($employee->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group m-t-20">
                            <button type="button" id="apply-filter" class="btn btn-success btn-block">@lang('app.apply')</button>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <div class="col-xs-12">
            <div class="row dashboard-stats">
                <div class="col-md-12 m-b-30">
                    <div class="white-box">
                        <div class="col-md-2  col-sm-4 text-center">
                            <h4><span class="text-dark" id="totalWorkingDays">{{ $totalWorkingDays }}</span> <span class="font-12 text-muted m-l-5"> @lang('modules.attendance.totalWorkingDays')</span></h4>
                        </div>
                        <div class="col-md-2 b-l  col-sm-4 text-center">
                            <h4><span class="text-success" id="daysPresent">{{ $daysPresent }}</span> <span class="font-12 text-muted m-l-5"> @lang('modules.attendance.daysPresent')</span></h4>
                        </div>
                        <div class="col-md-2 b-l  col-sm-4 text-center">
                            <h4><span class="text-danger" id="daysLate">{{ $daysLate }}</span> <span class="font-12 text-muted m-l-5"> @lang('app.days') @lang('modules.attendance.late')</span></h4>
                        </div>
                        <div class="col-md-2 b-l  col-sm-4 text-center">
                            <h4><span class="text-warning" id="halfDays">{{ $halfDays }}</span> <span class="font-12 text-muted m-l-5"> @lang('modules.attendance.halfDay')</span></h4>
                        </div>
                        <div class="col-md-2 b-l  col-sm-4 text-center">
                            <h4><span class="text-info" id="absentDays">{{ (($totalWorkingDays - $daysPresent) < 0) ? '0' : ($totalWorkingDays - $daysPresent) }}</span> <span class="font-12 text-muted m-l-5"> @lang('app.days') @lang('modules.attendance.absent')</span></h4>
                        </div>
                        <div class="col-md-2 b-l  col-sm-4 text-center">
                            <h4><span class="text-primary" id="holidayDays">{{ $holidays }}</span> <span class="font-12 text-muted m-l-5"> @lang('modules.attendance.holidays')</span></h4>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <div class="col-xs-12">
            <div class="white-box">

            <table class="table ">
                <thead>
                <tr>
                    <th width="11%">@lang('app.date')</th>
                    <th width="11%">@lang('app.status')</th>
                    <th colspan="7">
                        <table width="100%">
                                <tr>
                                    <th class="al-center" width="14%">@lang('modules.attendance.clock_in')</th>
                                    <th class="al-center" width="14%">@lang('modules.attendance.clock_out')</th>
                                    <th class="al-center" width="14%">@lang('modules.attendance.late')</th>
                                    <th class="al-center" width="14%">@lang('modules.attendance.early_leaving')</th>
                                    <th class="al-center" width="14%">@lang('modules.attendance.over_time')</th>
                                    <th class="al-center" width="14%">@lang('modules.attendance.total_work')</th>
                                    <th class="al-center">@lang('app.others')</th>
                                </tr>
                        </table>
                    </th>
                    
                </tr>
                </thead>
                <tbody id="attendanceData">
                </tbody>
            </table>
            </div>

        </div>

    </div>


@endsection

@push('footer-script')
<script src="{{ asset('plugins/bower_components/moment/moment.js') }}"></script>
<script src="{{ asset('plugins/bower_components/bootstrap-datepicker/bootstrap-datepicker.min.js') }}"></script>
<script src="{{ asset('plugins/bower_components/bootstrap-daterangepicker/daterangepicker.js') }}"></script>

<script src="{{ asset('plugins/bower_components/datatables/jquery.dataTables.min.js') }}"></script>
<script src="https://cdn.datatables.net/1.10.13/js/dataTables.bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.1.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.1.1/js/responsive.bootstrap.min.js"></script>

<script src="{{ asset('plugins/bower_components/custom-select/custom-select.min.js') }}"></script>
<script src="{{ asset('plugins/bower_components/bootstrap-select/bootstrap-select.min.js') }}"></script>

<script src="{{ asset('plugins/bower_components/waypoints/lib/jquery.waypoints.js') }}"></script>
<script src="{{ asset('plugins/bower_components/counterup/jquery.counterup.min.js') }}"></script>
<script src="{{ asset('plugins/bower_components/moment/moment.js') }}"></script>
<script type="text/javascript" src="{{ asset('plugins/daterange-picker/daterangepicker.js') }}"></script>
<script>
    var startDate = '{{ $startDate->format('Y-m-d') }}';
    var endDate = '{{ $endDate->format('Y-m-d') }}';
    $(function() {
        var dateformat = '{{ $global->moment_format }}';

        var startDate = '{{ $startDate->format('Y-m-d') }}';
        var start = moment(startDate, dateformat);

        var endDate = '{{ $endDate->format('Y-m-d') }}';
        var end = moment(endDate, dateformat);

        function cb(start, end) {
            $('#start-date').val(start.format(dateformat));
            $('#end-date').val(end.format(dateformat));
            $('#reportrange span').html(start.format(dateformat) + ' - ' + end.format(dateformat));
        }
        moment.locale('{{ $global->locale }}');
        $('#reportrange').daterangepicker({
            startDate: start,
            endDate: end,

            locale: {
                language: '{{ $global->locale }}',
                format: '{{ $global->moment_format }}',
            },
            linkedCalendars: false,
            ranges: dateRangePickerCustom
        }, cb);

        cb(start, end);

    });
    $('.input-daterange-datepicker').daterangepicker({
        buttonClasses: ['btn', 'btn-sm'],
        cancelClass: 'btn-inverse',
        "locale": {
            "applyLabel": "{{ __('app.apply') }}",
            "cancelLabel": "{{ __('app.cancel') }}",
            "daysOfWeek": [
                "{{ __('app.su') }}",
                "{{ __('app.mo') }}",
                "{{ __('app.tu') }}",
                "{{ __('app.we') }}",
                "{{ __('app.th') }}",
                "{{ __('app.fr') }}",
                "{{ __('app.sa') }}"
            ],
            "monthNames": [
                "{{ __('app.january') }}",
                "{{ __('app.february') }}",
                "{{ __('app.march') }}",
                "{{ __('app.april') }}",
                "{{ __('app.may') }}",
                "{{ __('app.june') }}",
                "{{ __('app.july') }}",
                "{{ __('app.august') }}",
                "{{ __('app.september') }}",
                "{{ __('app.october') }}",
                "{{ __('app.november') }}",
                "{{ __('app.december') }}",
            ],
            "firstDay": {{ $global->week_start }},
        }
    })
    $('#apply-filter').click(function () {
       showTable();
    });

    $(".select2").select2({
        formatNoMatches: function () {
            return "{{ __('messages.noRecordFound') }}";
        }
    });

    var table;

    function showTable() {

        var startDate = $('#start-date').val();
        var endDate = $('#end-date').val();

        var userId = $('#user_id').val();
        if (userId == "") {
            userId = 0;
        }

        //refresh counts
        var url = '{!!  route('admin.attendances.refreshCount', [':startDate', ':endDate', ':userId']) !!}';
        url = url.replace(':startDate', startDate);
        url = url.replace(':endDate', endDate);
        url = url.replace(':userId', userId);

        $.easyAjax({
            type: 'GET',
            url: url,
            success: function (response) {
                $('#daysPresent').html(response.daysPresent);
                $('#daysLate').html(response.daysLate);
                $('#halfDays').html(response.halfDays);
                $('#totalWorkingDays').html(response.totalWorkingDays);
                $('#absentDays').html(response.absentDays);
                $('#holidayDays').html(response.holidays);
                initConter();
            }
        });

        //refresh datatable
        var url2 = '{!!  route('admin.attendances.employeeData', [':startDate', ':endDate', ':userId']) !!}';

        url2 = url2.replace(':startDate', startDate);
        url2 = url2.replace(':endDate', endDate);
        url2 = url2.replace(':userId', userId);
        console.log(url2);
        $.easyAjax({
            type: 'GET',
            url: url2,
            success: function (response) {
                console.log(response);
                $('#attendanceData').html(response.data);
            }
        });
    }

    $('#attendanceData').on('click', '.delete-attendance', function(){
        var id = $(this).data('attendance-id');
        swal({
            title: "@lang('messages.sweetAlertTitle')",
            text: "@lang('messages.confirmation.recoverAttendance')",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "@lang('messages.deleteConfirmation')",
            cancelButtonText: "@lang('messages.confirmNoArchive')",
            closeOnConfirm: true,
            closeOnCancel: true
        }, function(isConfirm){
            if (isConfirm) {

                var url = "{{ route('admin.attendances.destroy',':id') }}";
                url = url.replace(':id', id);

                var token = "{{ csrf_token() }}";

                $.easyAjax({
                    type: 'POST',
                    url: url,
                    data: {'_token': token, '_method': 'DELETE'},
                    success: function (response) {
                        if (response.status == "success") {
                            $.unblockUI();
//                                    swal("Deleted!", response.message, "success");
                            showTable();
                        }
                    }
                });
            }
        });
    });

    function initConter() {
        $(".counter").counterUp({
            delay: 100,
            time: 1200
        });
    }

    showTable();
    
    function exportCsv(_this) {
        var startDate = $('#start-date').val();
        var endDate = $('#end-date').val();

        var userId = $('#user_id').val();
        if (userId == "") {
            userId = 0;
        }
        var url = '{!! route('admin.attendances.member.export_csv', [':startDate',':endDate',':userId',]) !!}';
        url = url.replace(':startDate', startDate);
        url = url.replace(':endDate', endDate);
        url = url.replace(':userId', userId);
        window.location.href = url;
    }

    function exportPdf(_this) {
        var startDate = $('#start-date').val();
        var endDate = $('#end-date').val();

        var userId = $('#user_id').val();
        if (userId == "") {
            userId = 0;
        }
        var url = '{!! route('admin.attendances.member.export_pdf', [':startDate',':endDate',':userId',]) !!}';
        url = url.replace(':startDate', startDate);
        url = url.replace(':endDate', endDate);
        url = url.replace(':userId', userId);
        window.location.href = url;
    }

    function print_member(_this){
        
        var print_ = "";
        var startDate = $('#start-date').val();
        var endDate = $('#end-date').val();

        var userId = $('#user_id').val();
        if (userId == "") {
            userId = 0;
        }

        
        var url = '{!! route('admin.attendances.memberPrintData') !!}';
        
        var token = "{{ csrf_token() }}";

        $.easyAjax({
            type: 'POST',
            data: {
                '_token': token,
                startDate: startDate,
                endDate: endDate,
                userId: userId
            },
            url: url,
            success: function (response) {
                console.log(response);
                print_ = response.data;
                win = window.open("","");
                win.document.write(print_);
                win.print();
                win.close();
            }
        });
        
        
    }

</script>
@endpush

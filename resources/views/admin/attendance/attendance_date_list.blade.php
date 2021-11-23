<div class="row">
    <div class="col-xs-12">
        <div class="panel panel-inverse">
            <div class="panel-heading">

                <div class="col-md-1 col-xs-3">
                    {!!  '<img src="'.$row->image_url.'" alt="user" class="img-circle" width="40" height="40">' !!}
                </div>
                <div class="col-md-11 col-xs-9">
                    {{ ucwords($row->name) }} <br>
                    <span class="font-light text-muted">{{ ucfirst($row->designation_name) }}</span>
                </div>
                <div class="clearfix"></div>

            </div>
            <div class="panel-wrapper collapse in" aria-expanded="true">
                <div class="panel-body">
                    <div class="row">
                        {!! Form::open(['id'=>'attendance-container-'.$row->id,'class'=>'ajax-form','method'=>'POST']) !!}
                        <div class="form-body ">

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label" >@lang('modules.attendance.late')</label>
                                        @if(count($row->attendance) > 0)
                                            @if($row->attendance[0]->late == "yes") <span class="label label-success"><i class="fa fa-check"></i> @lang('app.yes')</span>
                                            @else
                                                <span class="label label-danger"><i class="fa fa-times"></i> @lang('app.no')</span>
                                            @endif
                                        @else
                                            --
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label class="control-label" >@lang('modules.attendance.halfDay')</label>
                                        @if(count($row->attendance) > 0)
                                            @if($row->attendance[0]->half_day == "yes") <span class="label label-success"><i class="fa fa-check"></i> @lang('app.yes')</span>
                                            @else
                                                <span class="label label-danger"><i class="fa fa-times"></i> @lang('app.no')</span>
                                            @endif
                                        @else
                                            --
                                        @endif
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                        <tr>
                                            <th width="14%">@lang('modules.attendance.clock_in')</th>
                                            <th width="14%">@lang('modules.attendance.clock_out')</th>
                                            <th width="14%">@lang('modules.attendance.late')</th>
                                            <th width="14%">@lang('modules.attendance.early_leaving')</th>
                                            <th width="14%">@lang('modules.attendance.over_time')</th>
                                            <th width="14%">@lang('modules.attendance.total_work')</th>
                                            <th>@lang('app.others')</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($row->attendance as $index => $attendance)
                                            @php 
                                                $officeStartDate = \Carbon\Carbon::createFromFormat('H:i:s', $attendanceSettings->office_start_time);
                                                $officeEndDate = \Carbon\Carbon::createFromFormat('H:i:s', $attendanceSettings->office_end_time); 
                                            @endphp
                                            <tr>
                                                <td width="14%" class="al-center bt-border">
                                                    {{ $attendance->clock_in_time->timezone($global->timezone)->format($global->time_format) }}
                                                </td>
                                                <td width="14%" class="al-center bt-border">
                                                    @if(!is_null($attendance->clock_out_time)) {{ $attendance->clock_out_time->timezone($global->timezone)->format($global->time_format) }} @else - @endif
                                                </td>
                                                <td width="14%" class="al-center bt-border">
                                                    @if(($subLate = strtotime($attendance->clock_in_time->timezone($global->timezone)) % 86400 - strtotime($officeStartDate) % 86400) > 0)
                                                    {{
                                                        gmdate("H:i",$subLate)
                                                    }}
                                                    @else - @endif
                                                </td>
                                                <td width="14%" class="al-center bt-border">
                                                    @if(!is_null($attendance->clock_out_time)&&($subEarly = strtotime($officeEndDate) % 86400 - strtotime($attendance->clock_out_time->timezone($global->timezone))%86400) > 0)
                                                    {{
                                                        gmdate("H:i",$subEarly)
                                                    }}
                                                    @else - @endif
                                                </td>
                                                <td width="14%" class="al-center bt-border">
                                                    @if(!is_null($attendance->clock_out_time)&&($subEarly + $subLate) < 0)
                                                    {{
                                                        gmdate("H:i",86400 - ($subEarly + $subLate))
                                                    }}
                                                    @else - @endif
                                                </td>
                                                <td width="14%" class="al-center bt-border">
                                                    @if(!is_null($attendance->clock_out_time)&&($subTotal = strtotime($attendance->clock_out_time->timezone($global->timezone)) - strtotime($attendance->clock_in_time->timezone($global->timezone))) > 0)
                                                    {{
                                                        gmdate("H:i",$subTotal)
                                                    }}
                                                    @else - @endif
                                                </td>
                                                <td class="bt-border" style="padding-bottom: 5px;">
                                                    <strong>@lang('modules.attendance.clock_in') IP: </strong> {{ $attendance->clock_in_ip }}<br>
                                                    <strong>@lang('modules.attendance.clock_out') IP: </strong> {{ $attendance->clock_out_ip }}<br>
                                                    <strong>@lang('modules.attendance.working_from'): </strong> {{ $attendance->working_from }}<br>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7">@lang('messages.noAttendanceDetail')</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

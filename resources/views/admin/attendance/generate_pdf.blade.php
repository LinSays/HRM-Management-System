<!DOCTYPE html>
<html>
<head>
    <title>PDF Report</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }

        table td, table th {
            border: 1px solid #ddd;
            padding-top: 8px;
            padding-bottom:8px;
            text-align:center;
        }

        table tr:nth-child(even){background-color: #f2f2f2;}

        table th {
            padding-top: 12px;
            padding-bottom: 12px;
            background-color: #04AA6D;
            color: white;
        }
        td.table-col{
            font-size:10px;
        }
        .text-danger{
            color:#fb9678
        }
        .text-success{
            color:#00c292
        }
        .text-warning{
            color:#a16800
        }
        th.no-border{
            border:none
        }
    </style>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
</head>
<body>
    
    @if($type=='summary')
        <h1>Summary Attendance Report</h1>
    @elseif($type=="member")
        <h1>{{$member_name}}'s Attendance Report</h1>
    @elseif($type=="date")
        <h1>{{$date_title}}'s Attendance Report</h1>
    @endif
  <div>
    @if($type=='summary')
        <table class="table table-nowrap mb-0">
            <thead >
                <tr>
                    <th>@lang('app.employee')</th>
                    @for($i =1; $i <= $summary["daysInMonth"]; $i++)
                        <th>{{ $i }}</th>
                    @endfor
                    <th>@lang('app.total')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary["employeeAttendence"] as $key => $attendance) 
                    @php
                        $totalPresent = 0;
                    @endphp
                    <tr>
                        <td class="table-col">{{$attendance["employee_name"]}}</td>
                        @foreach($attendance as $key2=>$day)
                            @if($key2!="employee_name")
                                <td class="text-center table-col">
                                    @if($day == 'Absent')
                                        <span ><i class="fa fa-times text-danger"></i></span>
                                    @elseif($day == 'Holiday')
                                        <span><i class="fa fa-star text-warning"></i></span>
                                    @else
                                        @if($day != '-')
                                            @php
                                                $totalPresent = $totalPresent + 1;
                                            @endphp
                                            <span><i class="fa fa-check text-success"></i></span>
                                        @else
                                            <span>-</span>
                                        @endif
                                        
                                    @endif
                                </td>
                            @endif
                        @endforeach
                        <td class="total table-col text-success">{{$totalPresent.'/'.$summary["daysInMonth"]}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($type=="member")
        
        <table class="table ">
                <thead>
                <tr>
                    <th width="12.5%">@lang('app.date')</th>
                    <th width="12.5%">@lang('app.status')</th>
                    <th colspan="6">
                        <table width="100%">
                                <tr>
                                    <th class="no-border al-center" width="16.6%">@lang('modules.attendance.clock_in')</th>
                                    <th class="no-border al-center" width="16.6%">@lang('modules.attendance.clock_out')</th>
                                    <th class="no-border al-center" width="16.6%">@lang('modules.attendance.late')</th>
                                    <th class="no-border al-center" width="16.6%">@lang('modules.attendance.early_leaving')</th>
                                    <th class="no-border al-center" width="16.6%">@lang('modules.attendance.over_time')</th>
                                    <th class="no-border al-center" >@lang('modules.attendance.total_work')</th>
                                </tr>
                        </table>
                    </th>
                    
                </tr>
                </thead>
                <tbody id="attendanceData">
                    @foreach ($member as $key => $dateData)
                        @php
                            $currentDate = \Carbon\Carbon::parse($key);
                            $officeStartDate = \Carbon\Carbon::createFromFormat('H:i:s', $dateData['attendanceSetting']->office_start_time);
                            $officeEndDate = \Carbon\Carbon::createFromFormat('H:i:s', $dateData['attendanceSetting']->office_end_time);
                        @endphp
                        @if($dateData['attendance'])
                            <tr>
                                <td width="12.5%">
                                    {{ $currentDate->format($dateData['global']->date_format) }}
                                    <br>
                                    <label class="label label-success">{{ $currentDate->format('l') }}</label>
                                </td>
                                <td width="12.5%"><label class="label label-success ">@lang('modules.attendance.present')</label>
                                    @foreach ($dateData['attendance'] as $attendance)
                                        @if ($attendance->half_day == 'yes')
                                            <label class="label label-success ">@lang('modules.attendance.halfDay')</label>
                                        @endif
                                        @if ($attendance->late == 'yes')
                                            <label class="label label-danger ">@lang('modules.attendance.late')</label>
                                        @endif
                                    @endforeach
                                </td>
                                <td colspan="6">
                                    <table width="100%">
                                        @foreach($dateData['attendance'] as $attendance)
                                            <tr>
                                                <td width="16.6%" class="al-center bt-border">
                                                    {{ $attendance->clock_in_time->timezone($dateData['global']->timezone)->format($dateData['global']->time_format) }}
                                                </td>
                                                <td width="16.6%" class="al-center bt-border">
                                                    @if (!is_null($attendance->clock_out_time))
                                                        {{ $attendance->clock_out_time->timezone($dateData['global']->timezone)->format($dateData['global']->time_format) }}
                                                    @else - @endif
                                                </td>
                                                <td width="16.6%" class="al-center bt-border">
                                                    @if(($subLate = strtotime($attendance->clock_in_time->timezone($dateData['global']->timezone)) % 86400 - strtotime($officeStartDate) % 86400) > 0)
                                                    {{
                                                        gmdate("H:i",$subLate)
                                                    }}
                                                    @else - @endif
                                                </td>
                                                <td width="16.6%" class="al-center bt-border">
                                                    @if(!is_null($attendance->clock_out_time)&&($subEarly = strtotime($officeEndDate) % 86400 - strtotime($attendance->clock_out_time->timezone($dateData['global']->timezone))%86400) > 0)
                                                    {{
                                                        gmdate("H:i",$subEarly)
                                                    }}
                                                    @else - @endif
                                                </td>
                                                <td width="16.6%" class="al-center bt-border">
                                                    @if(!is_null($attendance->clock_out_time)&&($subEarly + $subLate) < 0)
                                                    {{
                                                        gmdate("H:i",86400 - ($subEarly + $subLate))
                                                    }}
                                                    @else - @endif
                                                </td>
                                                <td width="16.6%" class="al-center bt-border">
                                                    @if(!is_null($attendance->clock_out_time)&&($subTotal = strtotime($attendance->clock_out_time->timezone($dateData['global']->timezone)) - strtotime($attendance->clock_in_time->timezone($dateData['global']->timezone))) > 0)
                                                    {{
                                                        gmdate("H:i",$subTotal)
                                                    }}
                                                    @else - @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </td>

                            </tr>
                        @else
                            <tr>
                                <td width="12.5%">
                                    {{ $currentDate->format($dateData['global']->date_format) }}
                                    <br>
                                    <label class="label label-success">{{ $currentDate->format('l') }}</label>
                                </td>
                                <td width="12.5%">
                                    @if (!$dateData['holiday'] && !$dateData['leave'])
                                        <label class="label label-info">@lang('modules.attendance.absent')</label>
                                    @elseif($dateData['leave'])
                                        <label class="label label-primary">@lang('modules.attendance.leave')</label>
                                    @else
                                        <label class="label label-megna">@lang('modules.attendance.holiday')</label>
                                    @endif
                                    
                                </td>
                                <td colspan="6">
                                    <table width="100%">
                                        <tr>
                                            <td width="16.6%" class="al-center">-</td>
                                            <td width="16.6%" class="al-center">-</td>
                                            <td width="16.6%" class="al-center">-</td>
                                            <td width="16.6%" class="al-center">-</td>
                                            <td width="16.6%" class="al-center">-</td>
                                            <td width="16.6%" class="al-center">-</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            
                        @endif
                    @endforeach
                </tbody>
            </table>
        <style>
        .attendence-list{
            display: block;
        }
        </style>
    @elseif($type =="date")
        
        @foreach ($dateData as $employee)
       
            <div>
                <h4>{{$employee['employee_name']}}</h4>
                <table class="table ">
                    <thead>
                        <tr>
                            <th class="no-border al-center" width="16.6%">@lang('modules.attendance.clock_in')</th>
                            <th class="no-border al-center" width="16.6%">@lang('modules.attendance.clock_out')</th>
                            <th class="no-border al-center" width="16.6%">@lang('modules.attendance.late')</th>
                            <th class="no-border al-center" width="16.6%">@lang('modules.attendance.early_leaving')</th>
                            <th class="no-border al-center" width="16.6%">@lang('modules.attendance.over_time')</th>
                            <th class="no-border al-center" >@lang('modules.attendance.total_work')</th>
                            
                        </tr>
                    </thead>
                    <tbody id="attendanceData">
                        @foreach ($employee as $key => $dateData)
                            @if($key!="employee_name")
                                @php
                                    $currentDate = \Carbon\Carbon::parse($key);
                                    $officeStartDate = \Carbon\Carbon::createFromFormat('H:i:s', $dateData['attendanceSetting']->office_start_time);
                                    $officeEndDate = \Carbon\Carbon::createFromFormat('H:i:s', $dateData['attendanceSetting']->office_end_time);
                                @endphp
                                @if($dateData['attendance'])
                                    @foreach($dateData['attendance'] as $attendance)
                                        <tr>
                                            <td width="16.6%" class="al-center bt-border">
                                                {{ $attendance->clock_in_time->timezone($dateData['global']->timezone)->format($dateData['global']->time_format) }}
                                            </td>
                                            <td width="16.6%" class="al-center bt-border">
                                                @if (!is_null($attendance->clock_out_time))
                                                    {{ $attendance->clock_out_time->timezone($dateData['global']->timezone)->format($dateData['global']->time_format) }}
                                                @else - @endif
                                            </td>
                                            <td width="16.6%" class="al-center bt-border">
                                                @if(($subLate = strtotime($attendance->clock_in_time->timezone($dateData['global']->timezone)) % 86400 - strtotime($officeStartDate) % 86400) > 0)
                                                {{
                                                    gmdate("H:i",$subLate)
                                                }}
                                                @else - @endif
                                            </td>
                                            <td width="16.6%" class="al-center bt-border">
                                                @if(!is_null($attendance->clock_out_time)&&($subEarly = strtotime($officeEndDate) % 86400 - strtotime($attendance->clock_out_time->timezone($dateData['global']->timezone))%86400) > 0)
                                                {{
                                                    gmdate("H:i",$subEarly)
                                                }}
                                                @else - @endif
                                            </td>
                                            <td width="16.6%" class="al-center bt-border">
                                                @if(!is_null($attendance->clock_out_time)&&($subEarly + $subLate) < 0)
                                                {{
                                                    gmdate("H:i",86400 - ($subEarly + $subLate))
                                                }}
                                                @else - @endif
                                            </td>
                                            <td class="al-center bt-border">
                                                @if(!is_null($attendance->clock_out_time)&&($subTotal = strtotime($attendance->clock_out_time->timezone($dateData['global']->timezone)) - strtotime($attendance->clock_in_time->timezone($dateData['global']->timezone))) > 0)
                                                {{
                                                    gmdate("H:i",$subTotal)
                                                }}
                                                @else - @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td width="16.6%" class="al-center">-</td>
                                        <td width="16.6%" class="al-center">-</td>
                                        <td width="16.6%" class="al-center">-</td>
                                        <td width="16.6%" class="al-center">-</td>
                                        <td width="16.6%" class="al-center">-</td>
                                        <td  class="al-center">-</td>
                                    </tr>
                                    
                                @endif
                            @endif
                            
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif
  </div> 
</body>

</html>
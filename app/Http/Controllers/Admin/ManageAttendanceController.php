<?php

namespace App\Http\Controllers\Admin;

use App\Attendance;
use App\AttendanceSetting;
use App\EmployeeDetails;
use App\Helper\Reply;
use App\Holiday;
use App\Http\Requests\Attendance\StoreAttendance;
use App\Http\Requests\Attendance\StoreBulkAttendance;
use App\Leave;
use App\Project;
use App\ProjectMember;
use App\Team;
use App\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use PDF;
/**
 * Class ManageAttendanceController
 * @package App\Http\Controllers\Admin
 */
class ManageAttendanceController extends AdminBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.attendance';
        $this->pageIcon = 'icon-clock';
        $this->middleware(function ($request, $next) {
            abort_if(!in_array('attendance', $this->user->modules), 403);
            return $next($request);
        });


        // Getting Attendance setting data
        $this->attendanceSettings = AttendanceSetting::first();

        //Getting Maximum Check-ins in a day
        $this->maxAttandenceInDay = $this->attendanceSettings->clockin_in_day;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $openDays = json_decode($this->attendanceSettings->office_open_days);
        $this->startDate = Carbon::today()->timezone($this->global->timezone)->startOfMonth();
        $this->endDate = Carbon::now()->timezone($this->global->timezone);
        $this->employees = User::allEmployees();
        $this->userId = User::first()->id;

        $this->totalWorkingDays = $this->startDate->diffInDaysFiltered(function (Carbon $date) use ($openDays) {
            foreach ($openDays as $day) {
                if ($date->dayOfWeek == $day) {
                    return $date;
                }
            }
        }, $this->endDate);
        $this->daysPresent = Attendance::countDaysPresentByUser($this->startDate, $this->endDate, $this->userId);
        $this->daysLate = Attendance::countDaysLateByUser($this->startDate, $this->endDate, $this->userId);
        $this->halfDays = Attendance::countHalfDaysByUser($this->startDate, $this->endDate, $this->userId);
        $this->holidays = Count(Holiday::getHolidayByDates($this->startDate->format('Y-m-d'), $this->endDate->format('Y-m-d')));

        return view('admin.attendance.index', $this->data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.attendance.create', $this->data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreAttendance $request)
    {
        $date = Carbon::createFromFormat($this->global->date_format, $request->date)->format('Y-m-d');
        $clockIn = Carbon::createFromFormat('Y-m-d '.$this->global->time_format, $date.' '.$request->clock_in_time, $this->global->timezone);
        $clockIn->setTimezone('UTC');
        if ($request->clock_out_time != '') {
            $clockOut = Carbon::createFromFormat('Y-m-d '.$this->global->time_format, $date.' '.$request->clock_out_time, $this->global->timezone);
            $clockOut->setTimezone('UTC');

            if ($clockIn->gt($clockOut) && !is_null($clockOut)) {
                return Reply::error(__('messages.clockOutTimeError'));
            }

            $clockIn = $clockIn->toDateTimeString();
            $clockOut = $clockOut->toDateTimeString();

        } else {
            $clockOut = null;
        }

        $attendance = Attendance::where('user_id', $request->user_id)
            ->where(DB::raw('DATE(`clock_in_time`)'), $date)
            ->whereNull('clock_out_time')
            ->first();

        $clockInCount = Attendance::getTotalUserClockIn($date, $request->user_id);

        if (!is_null($attendance)) {
            $attendance->update([
                'user_id' => $request->user_id,
                'clock_in_time' => $clockIn,
                'clock_in_ip' => $request->clock_in_ip,
                'clock_out_time' => $clockOut,
                'clock_out_ip' => $request->clock_out_ip,
                'working_from' => $request->working_from,
                'late' => $request->late,
                'half_day' => $request->half_day
            ]);
        } else {

            // Check maximum attendance in a day
            if ($clockInCount < $this->attendanceSettings->clockin_in_day) {
                Attendance::create([
                    'user_id' => $request->user_id,
                    'clock_in_time' => $clockIn,
                    'clock_in_ip' => $request->clock_in_ip,
                    'clock_out_time' => $clockOut,
                    'clock_out_ip' => $request->clock_out_ip,
                    'working_from' => $request->working_from,
                    'late' => $request->late,
                    'half_day' => $request->half_day
                ]);
            } else {
                return Reply::error(__('messages.maxColckIn'));
            }
        }

        return Reply::success(__('messages.attendanceSaveSuccess'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $attendance = Attendance::find($id);

        $this->date = $attendance->clock_in_time->format('Y-m-d');
        $this->row = $attendance;
        $this->clock_in = 1;
        $this->userid = $attendance->user_id;
        $this->total_clock_in  = Attendance::where('user_id', $attendance->user_id)
            ->where(DB::raw('DATE(attendances.clock_in_time)'), '=', $this->date)
            ->whereNull('attendances.clock_out_time')->count();
        $this->type = 'edit';
        return view('admin.attendance.attendance_mark', $this->data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
        $date = Carbon::createFromFormat($this->global->date_format, $request->attendance_date)->format('Y-m-d');

        $clockIn = Carbon::createFromFormat('Y-m-d '.$this->global->time_format, $date.' '.$request->clock_in_time, $this->global->timezone);
        $clockIn->setTimezone('UTC');
        if ($request->clock_out_time != '') {
            $clockOut = Carbon::createFromFormat('Y-m-d '.$this->global->time_format, $date.' '.$request->clock_out_time, $this->global->timezone);
            $clockOut->setTimezone('UTC');
            
            if ($clockIn->gt($clockOut) && !is_null($clockOut)) {
                return Reply::error(__('messages.clockOutTimeError'));
            }

            $clockIn = $clockIn->toDateTimeString();
            $clockOut = $clockOut->toDateTimeString();

        } else {
            $clockOut = null;
        }

        $attendance->user_id = $request->user_id;
        $attendance->clock_in_time = $clockIn;
        $attendance->clock_in_ip = $request->clock_in_ip;
        $attendance->clock_out_time = $clockOut;
        $attendance->clock_out_ip = $request->clock_out_ip;
        $attendance->working_from = $request->working_from;
        $attendance->late = ($request->has('late')) ? 'yes' : 'no';
        $attendance->half_day = ($request->has('halfday'))? 'yes' : 'no';
        $attendance->save();

        return Reply::success(__('messages.attendanceSaveSuccess'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Attendance::destroy($id);
        return Reply::success(__('messages.attendanceDelete'));
    }

    public function data(Request $request)
    {

        $date = Carbon::createFromFormat($this->global->date_format, $request->date)->format('Y-m-d');
        $attendances = Attendance::attendanceByDate($date);

        return DataTables::of($attendances)
            ->editColumn('id', function ($row) {
                return view('admin.attendance.attendance_list', ['row' => $row, 'global' => $this->global, 'maxAttandenceInDay' => $this->maxAttandenceInDay])->render();
            })
            ->rawColumns(['id'])
            ->removeColumn('name')
            ->removeColumn('clock_in_time')
            ->removeColumn('clock_out_time')
            ->removeColumn('image')
            ->removeColumn('attendance_id')
            ->removeColumn('working_from')
            ->removeColumn('late')
            ->removeColumn('half_day')
            ->removeColumn('clock_in_ip')
            ->removeColumn('designation_name')
            ->removeColumn('total_clock_in')
            ->removeColumn('clock_in')
            ->make();
    }

    public function refreshCount(Request $request, $startDate = null, $endDate = null, $userId = null)
    {

        $openDays = json_decode($this->attendanceSettings->office_open_days);
        // $startDate = Carbon::createFromFormat('!Y-m-d', $startDate);
        // $endDate = Carbon::createFromFormat('!Y-m-d', $endDate)->addDay(1); //addDay(1) is hack to include end date
        $startDate = Carbon::createFromFormat($this->global->date_format, $request->startDate);
        $endDate = Carbon::createFromFormat($this->global->date_format, $request->endDate)->addDay(1); //addDay(1) is hack to include end date
        $userId = $request->userId;

        $totalWorkingDays = $startDate->diffInDaysFiltered(function (Carbon $date) use ($openDays) {
            foreach ($openDays as $day) {
                if ($date->dayOfWeek == $day) {
                    return $date;
                }
            }
        }, $endDate);
        $endDate = Carbon::createFromFormat($this->global->date_format, $request->endDate);
        $daysPresent = Attendance::countDaysPresentByUser($startDate, $endDate, $userId);
        $daysLate = Attendance::countDaysLateByUser($startDate, $endDate, $userId);
        $halfDays = Attendance::countHalfDaysByUser($startDate, $endDate, $userId);
        $daysAbsent = (($totalWorkingDays - $daysPresent) < 0) ? '0' : ($totalWorkingDays - $daysPresent);
        $holidays = Count(Holiday::getHolidayByDates($startDate->format('Y-m-d'), $endDate->format('Y-m-d')));

        return Reply::dataOnly(['daysPresent' => $daysPresent, 'daysLate' => $daysLate, 'halfDays' => $halfDays, 'totalWorkingDays' => $totalWorkingDays, 'absentDays' => $daysAbsent, 'holidays' => $holidays]);
    }

    public function employeeData(Request $request, $startDate = null, $endDate = null, $userId = null)
    {
        $ant = []; // Array For attendance Data indexed by similar date
        $dateWiseData = []; // Array For Combine Data

        $startDate = Carbon::createFromFormat($this->global->date_format, $request->startDate)->startOfDay();
        $endDate = Carbon::createFromFormat($this->global->date_format, $request->endDate)->endOfDay()->addDay(1);

        $attendances = Attendance::userAttendanceByDate($startDate, $endDate, $userId); // Getting Attendance Data
        $holidays = Holiday::getHolidayByDates($startDate, $endDate); // Getting Holiday Data

        // Getting Leaves Data
        $leavesDates = Leave::where('user_id', $userId)
            ->where('leave_date', '>=', $startDate)
            ->where('leave_date', '<=', $endDate)
            ->where('status', 'approved')
            ->select('leave_date', 'reason')
            ->get()->keyBy('date')->toArray();

        $holidayData = $holidays->keyBy('holiday_date');
        $holidayArray = $holidayData->toArray();

        // Set Date as index for same date clock-ins
        foreach ($attendances as $attand) {
            $ant[$attand->clock_in_date][] = $attand; // Set attendance Data indexed by similar date
        }

        $endDate = Carbon::createFromFormat($this->global->date_format, $request->endDate)->timezone($this->global->timezone);
        $startDate = Carbon::createFromFormat($this->global->date_format, $request->startDate)->timezone($this->global->timezone)->subDay();

        // Set All Data in a single Array
        for ($date = $endDate; $date->diffInDays($startDate) > 0; $date->subDay()) {

            // Set default array for record
            $dateWiseData[$date->toDateString()] = [
                'holiday' => false,
                'attendance' => false,
                'leave' => false
            ];

            // Set Holiday Data
            if (array_key_exists($date->toDateString(), $holidayArray)) {
                $dateWiseData[$date->toDateString()]['holiday'] = $holidayData[$date->toDateString()];
            }

            // Set Attendance Data
            if (array_key_exists($date->toDateString(), $ant)) {
                $dateWiseData[$date->toDateString()]['attendance'] = $ant[$date->toDateString()];
            }

            // Set Leave Data
            if (array_key_exists($date->toDateString(), $leavesDates)) {
                $dateWiseData[$date->toDateString()]['leave'] = $leavesDates[$date->toDateString()];
            }
            $dateWiseData[$date->toDateString()]['attendanceSetting'] = AttendanceSetting::first();
        }
        
        
        // Getting View data
        $view = view('admin.attendance.user_attendance', ['dateWiseData' => $dateWiseData, 'global' => $this->global])->render();

        return Reply::dataOnly(['status' => 'success', 'data' => $view]);
    }

    public function getEmployeeData(Request $request, $startDate = null, $endDate = null, $userId = null)
    {
        $ant = []; // Array For attendance Data indexed by similar date
        $dateWiseData = []; // Array For Combine Data

        $startDate = Carbon::createFromFormat($this->global->date_format, $request->startDate)->startOfDay();
        $endDate = Carbon::createFromFormat($this->global->date_format, $request->endDate)->endOfDay()->addDay(1);

        $attendances = Attendance::userAttendanceByDate($startDate, $endDate, $userId); // Getting Attendance Data
        $holidays = Holiday::getHolidayByDates($startDate, $endDate); // Getting Holiday Data

        // Getting Leaves Data
        $leavesDates = Leave::where('user_id', $userId)
            ->where('leave_date', '>=', $startDate)
            ->where('leave_date', '<=', $endDate)
            ->where('status', 'approved')
            ->select('leave_date', 'reason')
            ->get()->keyBy('date')->toArray();

        $holidayData = $holidays->keyBy('holiday_date');
        $holidayArray = $holidayData->toArray();

        // Set Date as index for same date clock-ins
        foreach ($attendances as $attand) {
            $ant[$attand->clock_in_date][] = $attand; // Set attendance Data indexed by similar date
        }

        $endDate = Carbon::createFromFormat($this->global->date_format, $request->endDate)->timezone($this->global->timezone);
        $startDate = Carbon::createFromFormat($this->global->date_format, $request->startDate)->timezone($this->global->timezone)->subDay();

        // Set All Data in a single Array
        for ($date = $endDate; $date->diffInDays($startDate) > 0; $date->subDay()) {

            // Set default array for record
            $dateWiseData[$date->toDateString()] = [
                'holiday' => false,
                'attendance' => false,
                'leave' => false
            ];

            // Set Holiday Data
            if (array_key_exists($date->toDateString(), $holidayArray)) {
                $dateWiseData[$date->toDateString()]['holiday'] = $holidayData[$date->toDateString()];
            }

            // Set Attendance Data
            if (array_key_exists($date->toDateString(), $ant)) {
                $dateWiseData[$date->toDateString()]['attendance'] = $ant[$date->toDateString()];
            }

            // Set Leave Data
            if (array_key_exists($date->toDateString(), $leavesDates)) {
                $dateWiseData[$date->toDateString()]['leave'] = $leavesDates[$date->toDateString()];
            }
            $dateWiseData[$date->toDateString()]['attendanceSetting'] = AttendanceSetting::first();
            $dateWiseData[$date->toDateString()]['global']=$this->global;

        }
        return $dateWiseData;
        // Getting View data
        //$view = view('admin.attendance.user_attendance', ['dateWiseData' => $dateWiseData, 'global' => $this->global])->render();
    }

    public function member_csv(Request $request, $startDate, $endDate, $userId)
    {
        $employees = User::with(
            ['attendance' => function ($query) use ($request) {
            }]
        )->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'users.image')
            ->where('roles.name', '<>', 'client')->groupBy('users.id');
        
        $employees = $employees->where('users.id', $request->userId)->get();
        
        $fileName = $employees[0]->name.'('.$startDate.'-'.$endDate.')'.'.csv';
        $dateWiseData = $this->getEmployeeData($request, $startDate, $endDate,$userId);
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );
        $columns = array('DATE', 'STATUS', 'CLOCK IN', 'CLOCK OUT','LATE','EARLY LEAVING','OVERTIME','TOTAL WORK','OTHERS');
        $callback = function() use($dateWiseData, $columns,$employees) {
            $file = fopen('php://output', 'w');
            fputcsv($file, array($employees[0]->name."'s Attendance"));
            fputcsv($file,array());
            
            fputcsv($file, $columns);
            foreach ($dateWiseData as $key => $dateData) {
                $row = array();
                $currentDate = \Carbon\Carbon::parse($key);
                $officeStartDate = \Carbon\Carbon::createFromFormat('H:i:s', $dateData['attendanceSetting']->office_start_time);
                $officeEndDate = \Carbon\Carbon::createFromFormat('H:i:s', $dateData['attendanceSetting']->office_end_time);
                if($dateData['attendance']){
                    $overOneAttendance = 0;
                    
                    foreach($dateData['attendance'] as $attendance){
                        $overOneAttendance++;
                        if($overOneAttendance==1){
                            $col1 = date("d-m-Y", strtotime($currentDate)).' '.date("D", strtotime($currentDate));
                            $col2 = "Present";
                            foreach ($dateData['attendance'] as $attendance){
                                if ($attendance->half_day == 'yes')
                                    $col2.=",Half Day";
                                if ($attendance->late == 'yes')
                                    $col2.=',Late';
                            }
                        }else{
                            $col1 = "";
                            $col2 = "";
                        }
                        $col3 = date("H:i",strtotime($attendance->clock_in_time->setTimezone($this->global->timezone)));
                        if(!is_null($attendance->clock_out_time))   
                            $col4 =  date("H:i",strtotime($attendance->clock_out_time->setTimezone($this->global->timezone)));
                        else
                            $col4 = "-";
                        if(($subLate = strtotime($attendance->clock_in_time->setTimezone($this->global->timezone)) % 86400 - strtotime($officeStartDate) % 86400) > 0)
                            $col5 = gmdate("H:i",$subLate);
                        else
                            $col5 = "-";
                        if(!is_null($attendance->clock_out_time)&&($subEarly = strtotime($officeEndDate) % 86400 - strtotime($attendance->clock_out_time->setTimezone($this->global->timezone)) %86400) > 0)
                            $col6 = gmdate("H:i",$subEarly);
                        else 
                            $col6="-";
                        if(!is_null($attendance->clock_out_time)&&($subEarly + $subLate) < 0)
                            $col7=gmdate("H:i",86400 - ($subEarly + $subLate));
                        else 
                            $col7="";
                        if(!is_null($attendance->clock_out_time)&&($subTotal = strtotime($attendance->clock_out_time) - strtotime($attendance->clock_in_time)) > 0)
                            $col8 = gmdate("H:i",$subTotal);
                        else
                            $col8="-";
                        $col9 = "ClockInIp:".$attendance->clock_in_ip.' ClockOutIp:'.$attendance->clock_out_ip."Working From:".$attendance->working_from;
                        
                        $row = array($col1,$col2,$col3,$col4,$col5,$col6,$col7,$col8,$col9);   
                        fputcsv($file, $row);
                    }
                    

                }else{
                    $col1 = date("d-m-Y", strtotime($currentDate)).' '.date("D", strtotime($currentDate));
                    if (!$dateData['holiday'] && !$dateData['leave'])
                        $col2 = "absent";
                    else if($dateData['leave'])
                        $col2 = "leave";
                    else
                        $col2 = "absent";
                    $col3 = "-";
                    $col4 = "-";
                    $col5 = "-";
                    $col6 = "-";
                    $col7 = "-";
                    $col8 = "-";
                    if ($dateData['holiday'] && !$dateData['leave'])
                        $col9 = "Holiday for ".ucwords($dateData['holiday']->occassion);
                    else if($dateData['leave'])
                        $col9 = "Leave for ".ucwords($dateData['leave']['reason']);
                    else 
                        $col9 = "-";

                    $row = array($col1,$col2,$col3,$col4,$col5,$col6,$col7,$col8,$col9);   
                    fputcsv($file, $row);
                }    
                
                
            
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function member_pdf(Request $request, $startDate, $endDate, $userId)
    {
        $employees = User::with(
            ['attendance' => function ($query) use ($request) {
            }]
        )->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'users.image')
            ->where('roles.name', '<>', 'client')->groupBy('users.id');
        
        $employees = $employees->where('users.id', $request->userId)->get();
        $fileName = $employees[0]->name.'_attendance.pdf';
        $member = $this->getEmployeeData($request, $startDate, $endDate, $userId);
        $data['type'] = "member";
        $data["member"] = $member;
        $data["member_name"] =  $employees[0]->name;
        $pdf = PDF::loadView('admin.attendance.generate_pdf', $data);
        
        return $pdf->download($fileName);
    }

    public function memberPrintData(Request $request){
        $employees = User::with(
            ['attendance' => function ($query) use ($request) {
            }]
        )->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'users.image')
            ->where('roles.name', '<>', 'client')->groupBy('users.id');
            
        $employees = $employees->where('users.id', $request->userId)->get();
        $this->membername = $employees[0]->name;
        $this->print_type = "member";
       
        $this->member = $this->getEmployeeData($request, $request->startDate, $request->endDate, $request->userId);

        $view = view('admin.attendance.print', $this->data)->render();
        return Reply::dataOnly(['status' => 'success', 'data' => $view]);
    }

    public function attendanceByDate()
    {
        return view('admin.attendance.by_date', $this->data);
    }

    public function byDateData(Request $request)
    {
        $date = Carbon::createFromFormat($this->global->date_format, $request->date)->format('Y-m-d');
        $attendances = Attendance::attendanceDate($date)->get();
        $this->attendanceSettings = AttendanceSetting::first();
        return DataTables::of($attendances)
            ->editColumn('id', function ($row) {
                return view('admin.attendance.attendance_date_list', ['row' => $row,'attendanceSettings'=>$this->attendanceSettings, 'global' => $this->global])->render();
            })
            ->rawColumns(['id'])
            ->removeColumn('name')
            ->removeColumn('clock_in_time')
            ->removeColumn('clock_out_time')
            ->removeColumn('image')
            ->removeColumn('attendance_id')
            ->removeColumn('working_from')
            ->removeColumn('late')
            ->removeColumn('half_day')
            ->removeColumn('clock_in_ip')
            ->removeColumn('designation_name')
            ->make();
    }

    public function dateAttendanceCount(Request $request)
    {
        $date = Carbon::createFromFormat($this->global->date_format, $request->date)->format('Y-m-d');
        $checkHoliday = Holiday::checkHolidayByDate($date);
        $totalPresent = 0;
        $totalAbsent  = 0;
        $holiday  = 0;
        $holidayReason  = '';
        $totalEmployees = count(User::allEmployees());

        if (!$checkHoliday) {
            $totalPresent = Attendance::where(DB::raw('DATE(`clock_in_time`)'), '=', $date)->count();
            $totalAbsent = ($totalEmployees - $totalPresent);
        } else {
            $holiday = 1;
            $holidayReason = $checkHoliday->occassion;
        }

        return Reply::dataOnly(['status' => 'success', 'totalEmployees' => $totalEmployees, 'totalPresent' => $totalPresent, 'totalAbsent' => $totalAbsent, 'holiday' => $holiday, 'holidayReason' => $holidayReason]);
    }
    public function getEmployeeDataByDate(Request $request, $date = null, $userId = null)
    {
        $employees = User::with(
            ['attendance' => function ($query) use ($request) {
                $query->whereRaw('MONTH(attendances.clock_in_time) = ?', [$request->month])
                    ->whereRaw('YEAR(attendances.clock_in_time) = ?', [$request->year]);
            }]
        )->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'users.image')
            ->where('roles.name', '<>', 'client')->groupBy('users.id');
        $employees = $employees->where('users.id', $userId)->get();
        
        $ant = []; // Array For attendance Data indexed by similar date
        $dateWiseData = []; // Array For Combine Data

        $startDate = Carbon::createFromFormat($this->global->date_format, $date)->startOfDay();
        $endDate = Carbon::createFromFormat($this->global->date_format, $date)->endOfDay();

        $attendances = Attendance::userAttendanceByDate($startDate, $endDate, $userId); // Getting Attendance Data
        $holidays = Holiday::getHolidayByDates($startDate, $endDate); // Getting Holiday Data

        // Getting Leaves Data
        $leavesDates = Leave::where('user_id', $userId)
            ->where('leave_date', '>=', $startDate)
            ->where('leave_date', '<=', $endDate)
            ->where('status', 'approved')
            ->select('leave_date', 'reason')
            ->get()->keyBy('date')->toArray();

        $holidayData = $holidays->keyBy('holiday_date');
        $holidayArray = $holidayData->toArray();

        // Set Date as index for same date clock-ins
        foreach ($attendances as $attand) {
            $ant[$attand->clock_in_date][] = $attand; // Set attendance Data indexed by similar date
        }

        $endDate = Carbon::createFromFormat($this->global->date_format, $date)->timezone($this->global->timezone);
        $startDate = Carbon::createFromFormat($this->global->date_format, $date)->timezone($this->global->timezone)->subDay();
        
        // Set All Data in a single Array
        for ($date = $endDate; $date->diffInDays($startDate) > 0; $date->subDay()) {
            // Set default array for record
            $dateWiseData[$date->toDateString()] = [
                'holiday' => false,
                'attendance' => false,
                'leave' => false
            ];

            // Set Holiday Data
            if (array_key_exists($date->toDateString(), $holidayArray)) {
                $dateWiseData[$date->toDateString()]['holiday'] = $holidayData[$date->toDateString()];
            }

            // Set Attendance Data
            if (array_key_exists($date->toDateString(), $ant)) {
                $dateWiseData[$date->toDateString()]['attendance'] = $ant[$date->toDateString()];
            }

            // Set Leave Data
            if (array_key_exists($date->toDateString(), $leavesDates)) {
                $dateWiseData[$date->toDateString()]['leave'] = $leavesDates[$date->toDateString()];
            }
            $dateWiseData[$date->toDateString()]['attendanceSetting'] = AttendanceSetting::first();
            $dateWiseData[$date->toDateString()]['global']=$this->global;
            

        }
        $dateWiseData["employee_name"] = $employees[0]->name;
        return $dateWiseData;
        // Getting View data
        //$view = view('admin.attendance.user_attendance', ['dateWiseData' => $dateWiseData, 'global' => $this->global])->render();
    }
    public function date_csv(Request $request, $date)
    {
        $employees = User::with(
            ['attendance' => function ($query) use ($request) {
            }]
        )->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'users.image')
            ->where('roles.name', '<>', 'client')->groupBy('users.id');
        $employees = $employees->get();
        $date_title = Carbon::createFromFormat($this->global->date_format, $date)->format('Y-m-d');
        $fileName = 'attendanceByDate-'.$date_title.'.csv';
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );
        $columns = array('CLOCK IN', 'CLOCK OUT','LATE','EARLY LEAVING','OVERTIME','TOTAL WORK','OTHERS');
        $callback = function() use($employees, $columns,$date,$request,$date_title) {
            $file = fopen('php://output', 'w');

            fputcsv($file, array($date_title,"","","","","",""));
            fputcsv($file, array("","","","","","",""));
            fputcsv($file, $columns);

            $row = array();
            foreach ($employees as $employee) {
                $row = array($employee->name,"","","","","","");
                fputcsv($file, $row);
        
                $row = array();
                $dateWiseData = $this->getEmployeeDataByDate($request,$date,json_encode($employee->id));
                foreach ($dateWiseData as $key => $dateData) {
                    if($key!="employee_name"){
                        if($dateData['attendance']){
                            foreach($dateData['attendance'] as $attendance){
                            
                                $currentDate = \Carbon\Carbon::parse($key);
                                $officeStartDate = \Carbon\Carbon::createFromFormat('H:i:s', $dateData['attendanceSetting']->office_start_time);
                                $officeEndDate = \Carbon\Carbon::createFromFormat('H:i:s', $dateData['attendanceSetting']->office_end_time);
                                $col1 = date("H:i",strtotime($attendance->clock_in_time->setTimezone($this->global->timezone)));
                                if(!is_null($attendance->clock_out_time))   
                                    $col2 =  date("H:i",strtotime($attendance->clock_out_time->setTimezone($this->global->timezone)));
                                else
                                    $col2 = "-";
                                if(($subLate = strtotime($attendance->clock_in_time->setTimezone($this->global->timezone)) % 86400 - strtotime($officeStartDate) % 86400) > 0)
                                    $col3 = gmdate("H:i",$subLate);
                                else
                                    $col3 = "-";
                                if(!is_null($attendance->clock_out_time)&&($subEarly = strtotime($officeEndDate) % 86400 - strtotime($attendance->clock_out_time->setTimezone($this->global->timezone)) %86400) > 0)
                                    $col4 = gmdate("H:i",$subEarly);
                                else 
                                    $col4="-";
                                if(!is_null($attendance->clock_out_time)&&($subEarly + $subLate) < 0)
                                    $col5=gmdate("H:i",86400 - ($subEarly + $subLate));
                                else 
                                    $col5="";
                                if(!is_null($attendance->clock_out_time)&&($subTotal = strtotime($attendance->clock_out_time) - strtotime($attendance->clock_in_time)) > 0)
                                    $col6 = gmdate("H:i",$subTotal);
                                else
                                    $col6="-";
                                $col7 = "ClockInIp:".$attendance->clock_in_ip.' ClockOutIp:'.$attendance->clock_out_ip."Working From:".$attendance->working_from;
                                
                                $row = array($col1,$col2,$col3,$col4,$col5,$col6,$col7);   
                                fputcsv($file, $row);
                            
                            }
                        }else{
                            
                            $col1 = "-";
                            $col2 = "-";
                            $col3 = "-";
                            $col4 = "-";
                            $col5 = "-";
                            $col6 = "-";
                            if ($dateData['holiday'] && !$dateData['leave'])
                                $col7 = "Holiday for ".ucwords($dateData['holiday']->occassion);
                            else if($dateData['leave'])
                                $col7 = "Leave for ".ucwords($dateData['leave']['reason']);
                            else 
                                $col7 = "-";
        
                            $row = array($col1,$col2,$col3,$col4,$col5,$col6,$col7);   
                            fputcsv($file, $row);
                        }  
                    }
                }
                $row = array("","","","","","","");
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function date_pdf(Request $request, $date)
    {
        
        $fileName = $date.'_attendance.pdf';
        $dateData = array();

        $employees = User::with(
            ['attendance' => function ($query) use ($request) {
            }]
        )->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'users.image')
            ->where('roles.name', '<>', 'client')->groupBy('users.id');
        $employees = $employees->get();
        foreach($employees as $employee){
            $dateDataByEmployee = $this->getEmployeeDataByDate($request, $date, json_encode($employee->id));
            array_push($dateData, $dateDataByEmployee);
        }
        $data['type'] = "date";
        $data['date_title'] = Carbon::createFromFormat($this->global->date_format, $date)->format('Y-m-d');
        $data["dateData"] = $dateData;
        $pdf = PDF::loadView('admin.attendance.generate_pdf', $data);
        
        return $pdf->download($fileName);
    }

    public function datePrintData(Request $request){
        $this->print_type = "date";
        $this->date_title = Carbon::createFromFormat($this->global->date_format, $request->date)->format('Y-m-d');
        $employees = User::with(
            ['attendance' => function ($query) use ($request) {
            }]
        )->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'users.image')
            ->where('roles.name', '<>', 'client')->groupBy('users.id');
        $employees = $employees->get();
        $dateData_ = array();
        foreach($employees as $employee){

            $dateDataByEmployee = $this->getEmployeeDataByDate($request, $request->date, json_encode($employee->id));
            array_push($dateData_, $dateDataByEmployee);
        }
        $this->dateData = $dateData_;
        $view = view('admin.attendance.print', $this->data)->render();
        return Reply::dataOnly(['status' => 'success', 'data' => $view]);
    }

    public function checkHoliday(Request $request)
    {
        $date = Carbon::createFromFormat($this->global->date_format, $request->date)->format('Y-m-d');
        $checkHoliday = Holiday::checkHolidayByDate($date);
        return Reply::dataOnly(['status' => 'success', 'holiday' => $checkHoliday]);
    }

    // Attendance Detail Show
    public function attendanceDetail(Request $request)
    {

        // Getting Attendance Data By User And Date
        $this->attendances = Attendance::attedanceByUserAndDate($request->date, $request->userID);
        return view('admin.attendance.attendance-detail', $this->data)->render();
    }

    // Bulk Attendance Store
    public function bulkAttendanceStore(StoreBulkAttendance $request): array
    {
        $groups = $request->group_id;
        $employeeData = $request->user_id;
        $groupEmployeeData = [];
        $employees = [];
        if($groups)
        {
            $groupEmployeeData = User::join('employee_details', 'users.id', '=', 'employee_details.user_id')
                ->whereIn('employee_details.department_id', $groups)
                ->where('users.status', 'active')
                ->select('users.id')->pluck('users.id')->toArray();
        }

        if($employeeData)
        {
            $employees = $request->user_id;
        }

        $date = Carbon::createFromFormat('d-m-Y', '01-' . $request->month . '-' . $request->year)->format('Y-m-d');
        $clockIn = Carbon::createFromFormat('Y-m-d ' . $this->global->time_format, $date . ' ' . $request->clock_in_time, $this->global->timezone);
        $clockIn->setTimezone('UTC');
        if ($request->clock_out_time != '') {
            $clockOut = Carbon::createFromFormat('Y-m-d ' . $this->global->time_format, $date . ' ' . $request->clock_out_time, $this->global->timezone);
            $clockOut->setTimezone('UTC');
            if ($clockIn->gt($clockOut) && !is_null($clockOut)) {
                return Reply::error(__('messages.clockOutTimeError'));
            }
            $clockIn = $clockIn->toDateTimeString();
            $clockOut = $clockOut->toDateTimeString();
        }

        $startDate = Carbon::createFromFormat('d-m-Y', '01-' . $request->month . '-' . $request->year)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $period = CarbonPeriod::create($startDate, $endDate);
        $holidays = Holiday::getHolidayByDates($startDate->format('Y-m-d'), $endDate->format('Y-m-d'))->pluck('holiday_date')->toArray();

        if($groupEmployeeData)
        {
            $this->bulkAttendanceMark($groupEmployeeData, $period, $holidays, $request);
        }
        if($employees)
        {
            $this->bulkAttendanceMark($employees, $period, $holidays, $request);
        }

        return Reply::redirect(route('admin.attendances.summary'), __('messages.attendanceSaveSuccess'));
    }

    // Bulk attendance store action.
    public function bulkAttendanceMark($employees,$period, $holidays, $request )
    {
        $currentDate = Carbon::now();
        $insertData = [];
        foreach ($employees as $key => $userId) {
            foreach ($period as $date) {
                $attendance = Attendance::where('user_id', $userId)
                    ->where(DB::raw('DATE(`clock_in_time`)'), $date->format('Y-m-d'))
                    ->first();
                if (is_null($attendance) && $date->lt($currentDate)) { //attendance should not exist for the user for the same date
                    if (!in_array($date->format('Y-m-d'), $holidays)) { // date should not be a holiday
                        $clockIn = Carbon::createFromFormat('Y-m-d ' . $this->global->time_format, $date->format('Y-m-d') . ' ' . $request->clock_in_time, $this->global->timezone);
                        $clockIn->setTimezone('UTC');
                        $clockOut = Carbon::createFromFormat('Y-m-d ' . $this->global->time_format, $date->format('Y-m-d') . ' ' . $request->clock_out_time, $this->global->timezone);
                        $clockOut->setTimezone('UTC');
                        $insertData[] = [
                            'user_id' => $userId,
                            'clock_in_time' => $clockIn,
                            'clock_in_ip' => request()->ip(),
                            'clock_out_time' => $clockOut,
                            'clock_out_ip' => request()->ip(),
                            'working_from' => $request->working_from,
                            'late' => $request->late,
                            'half_day' => $request->half_day,
                            'company_id' => company()->id
                        ];
                    }
                }
            }
        }
        Attendance::insertOrIgnore($insertData);
    }

    // Attendance Detail Show
    public function bulkAttendance(Request $request)
    {
        // Getting Attendance Data By User And Date
        $this->employees = User::join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at')
            ->where('roles.name', 'employee')
            ->groupBy('users.id')
            ->distinct('users.id')
            ->get();
        $now = Carbon::now();
        $this->year = $now->format('Y');
        $this->month = $now->format('m');

        $this->groups = Team::all();

        return view('admin.attendance.bulk-attendance', $this->data)->render();
    }

    public function summary()
    {
        $this->employees = User::allEmployees();
        $now = Carbon::now();
        $this->year = $now->format('Y');
        $this->month = $now->format('m');
        return view('admin.attendance.summary', $this->data);
    }

    public function summaryData(Request $request)
    {
        $employees = User::with(
            ['attendance' => function ($query) use ($request) {
                $query->whereRaw('MONTH(attendances.clock_in_time) = ?', [$request->month])
                    ->whereRaw('YEAR(attendances.clock_in_time) = ?', [$request->year]);
            }]
        )->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'users.image')
            ->where('roles.name', '<>', 'client')->groupBy('users.id');
        if ($request->userId == '0') {
            $employees = $employees->get();
        } else {
            $employees = $employees->where('users.id', $request->userId)->get();
        }
        $this->holidays = Holiday::whereRaw('MONTH(holidays.date) = ?', [$request->month])->whereRaw('YEAR(holidays.date) = ?', [$request->year])->get();

        $final = [];

        $this->daysInMonth = Carbon::parse('01-' . $request->month . '-' . $request->year)->daysInMonth;
        $month = Carbon::parse('01-' . $request->month . '-' . $request->year)->lastOfMonth();
        $now = Carbon::now()->timezone($this->global->timezone);
        $requestedDate = Carbon::parse(Carbon::parse('01-' . $request->month . '-' . $request->year))->endOfMonth();

        foreach ($employees as $employee) {

            if($requestedDate->isPast()){
                $dataTillToday = array_fill(1, $this->daysInMonth, 'Absent');
            }
            else{
                $dataTillToday = array_fill(1, $now->copy()->format('d'), 'Absent');
            }

            $dataFromTomorrow = [];
            if (($now->copy()->addDay()->format('d') != $this->daysInMonth) && !$requestedDate->isPast()) {
                $dataFromTomorrow = array_fill($now->copy()->addDay()->format('d'), ($this->daysInMonth - $now->copy()->format('d')), '-');
            } else {
                if($this->daysInMonth < $now->copy()->format('d')){
                    $dataFromTomorrow = array_fill($month->copy()->addDay()->format('d'), (0), 'Absent');
                }
                else{
                    $dataFromTomorrow = array_fill($month->copy()->addDay()->format('d'), ($this->daysInMonth - $now->copy()->format('d')), 'Absent');
                }
            }
            $final[$employee->id . '#' . $employee->name] = array_replace($dataTillToday, $dataFromTomorrow);

            foreach ($employee->attendance as $attendance) {
                $final[$employee->id . '#' . $employee->name][Carbon::parse($attendance->clock_in_time)->timezone($this->global->timezone)->day] = '<a href="javascript:;" class="view-attendance" data-attendance-id="' . $attendance->id . '"><i class="fa fa-check text-success"></i></a>';
            }

            $image = '<img src="' . $employee->image_url . '" alt="user" class="img-circle" width="30" height="30"> ';
            $final[$employee->id . '#' . $employee->name][] = '<a class="userData" id="userID' . $employee->id . '" data-employee-id="' . $employee->id . '"  href="' . route('admin.employees.show', $employee->id) . '">' . $image . ' ' . ucwords($employee->name) . '</a>';

            foreach ($this->holidays as $holiday) {
                if ($final[$employee->id . '#' . $employee->name][$holiday->date->day] == 'Absent') {
                    $final[$employee->id . '#' . $employee->name][$holiday->date->day] = 'Holiday';
                }
            }
        }


        $this->employeeAttendence = $final;

        $view = view('admin.attendance.summary_data', $this->data)->render();
        return Reply::dataOnly(['status' => 'success', 'data' => $view]);
    }
    public function getSummaryData( Request $request,$year, $month, $userId){
        $employees = User::with(
            ['attendance' => function ($query) use ($year,$month) {
                $query->whereRaw('MONTH(attendances.clock_in_time) = ?', [$month])
                    ->whereRaw('YEAR(attendances.clock_in_time) = ?', [$year]);
            }]
        )->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'users.image')
            ->where('roles.name', '<>', 'client')->groupBy('users.id');
            
        if ($userId == '0') {
            $employees = $employees->get();
        } else {
            $employees = $employees->where('users.id', $userId)->get();
        }
       
        

        $this->holidays = Holiday::whereRaw('MONTH(holidays.date) = ?', [$month])->whereRaw('YEAR(holidays.date) = ?', [$year])->get();
        
        $final = [];

        $this->daysInMonth = Carbon::parse('01-' . $month . '-' . $year)->daysInMonth;
        $month_current = Carbon::parse('01-' . $month . '-' . $year)->lastOfMonth();
        $now = Carbon::now()->timezone($this->global->timezone);
        $requestedDate = Carbon::parse(Carbon::parse('01-' . $month . '-' . $year))->endOfMonth();
        

        foreach ($employees as $employee) {

            if($requestedDate->isPast()){
                $dataTillToday = array_fill(1, $this->daysInMonth, 'Absent');
            }
            else{
                $dataTillToday = array_fill(1, $now->copy()->format('d'), 'Absent');
            }

            $dataFromTomorrow = [];
            if (($now->copy()->addDay()->format('d') != $this->daysInMonth) && !$requestedDate->isPast()) {
                $dataFromTomorrow = array_fill($now->copy()->addDay()->format('d'), ($this->daysInMonth - $now->copy()->format('d')), '-');
            } else {
                if($this->daysInMonth < $now->copy()->format('d')){
                    $dataFromTomorrow = array_fill($month_current->copy()->addDay()->format('d'), (0), 'Absent');
                }
                else{
                    $dataFromTomorrow = array_fill($month_current->copy()->addDay()->format('d'), ($this->daysInMonth - $now->copy()->format('d')), 'Absent');
                }
            }
            $final[$employee->id . '#' . $employee->name] = array_replace($dataTillToday, $dataFromTomorrow);

            foreach ($employee->attendance as $attendance) {
                $final[$employee->id . '#' . $employee->name][Carbon::parse($attendance->clock_in_time)->timezone($this->global->timezone)->day] = 'attended';
            }

            $final[$employee->id . '#' . $employee->name]['employee_name'] = ucwords($employee->name);
            // $final[$employee->id . '#' . $employee->name]['avatar_url'] = $employee->image_url;
            foreach ($this->holidays as $holiday) {
                if ($final[$employee->id . '#' . $employee->name][$holiday->date->day] == 'Absent') {
                    $final[$employee->id . '#' . $employee->name][$holiday->date->day] = 'Holiday';
                }
            }
        }

        $this->employeeAttendence = $final;
        return $this->data;
    }
    public function summaryPrintData(Request $request){
        $employees = User::with(
            ['attendance' => function ($query) use ($request) {
                $query->whereRaw('MONTH(attendances.clock_in_time) = ?', [$request->month])
                    ->whereRaw('YEAR(attendances.clock_in_time) = ?', [$request->year]);
            }]
        )->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'users.image')
            ->where('roles.name', '<>', 'client')->groupBy('users.id');
            
        if ($request->userId == '0') {
            $employees = $employees->get();
        } else {
            $employees = $employees->where('users.id', $request->userId)->get();
        }
        $this->holidays = Holiday::whereRaw('MONTH(holidays.date) = ?', [$request->month])->whereRaw('YEAR(holidays.date) = ?', [$request->year])->get();

        $final = [];

        $this->daysInMonth = Carbon::parse('01-' . $request->month . '-' . $request->year)->daysInMonth;
        $month = Carbon::parse('01-' . $request->month . '-' . $request->year)->lastOfMonth();
        $now = Carbon::now()->timezone($this->global->timezone);
        $requestedDate = Carbon::parse(Carbon::parse('01-' . $request->month . '-' . $request->year))->endOfMonth();

        foreach ($employees as $employee) {

            if($requestedDate->isPast()){
                $dataTillToday = array_fill(1, $this->daysInMonth, 'Absent');
            }
            else{
                $dataTillToday = array_fill(1, $now->copy()->format('d'), 'Absent');
            }

            $dataFromTomorrow = [];
            if (($now->copy()->addDay()->format('d') != $this->daysInMonth) && !$requestedDate->isPast()) {
                $dataFromTomorrow = array_fill($now->copy()->addDay()->format('d'), ($this->daysInMonth - $now->copy()->format('d')), '-');
            } else {
                if($this->daysInMonth < $now->copy()->format('d')){
                    $dataFromTomorrow = array_fill($month->copy()->addDay()->format('d'), (0), 'Absent');
                }
                else{
                    $dataFromTomorrow = array_fill($month->copy()->addDay()->format('d'), ($this->daysInMonth - $now->copy()->format('d')), 'Absent');
                }
            }
            $final[$employee->id . '#' . $employee->name] = array_replace($dataTillToday, $dataFromTomorrow);

            foreach ($employee->attendance as $attendance) {
                $final[$employee->id . '#' . $employee->name][Carbon::parse($attendance->clock_in_time)->timezone($this->global->timezone)->day] = '<span class="text-success">Y</span>';
            }

            $image = '<img src="' . $employee->image_url . '" alt="user" class="img-circle" width="30" height="30"> ';
            $final[$employee->id . '#' . $employee->name][] = '<span  style="text-align:center">' . ucwords($employee->name) . '</span>';

            foreach ($this->holidays as $holiday) {
                if ($final[$employee->id . '#' . $employee->name][$holiday->date->day] == 'Absent') {
                    $final[$employee->id . '#' . $employee->name][$holiday->date->day] = 'Holiday';
                }
            }
        }


        $this->employeeAttendence = $final;
        $this->print_type = "summary";

        $view = view('admin.attendance.print', $this->data)->render();
        return Reply::dataOnly(['status' => 'success', 'data' => $view]);
    }

    

    public function summary_csv(Request $request, $year, $month, $userId)
    {
        $fileName = 'Summary_attendance.csv';
        $summary = $this->getSummaryData($request, $year, $month,$userId);
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = array();

        array_push($columns,'Employee');
        for($i=1;$i<=$summary['daysInMonth'];$i++){
            array_push($columns, $i);
        }
        array_push($columns,"TOTAL");
        $callback = function() use($summary, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($summary["employeeAttendence"] as $key => $attendance) {
                $totalPresent = 0;
                $row = array();
                array_push($row, $attendance['employee_name']);                
                foreach($attendance as $key2=>$day){
                    if($key2!="employee_name"){
                        array_push($row,$day);
                        if($day !='Absent' && $day !='Holiday' && $day !='-')
                            $totalPresent++;
                    }
                }
                $total = $totalPresent.' of '.$summary['daysInMonth'];
                array_push($row, $total);
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    

    public function summary_pdf(Request $request, $year, $month, $userId)
    {
        $fileName = 'Summary_attendance.pdf';
        $summary = $this->getSummaryData($request, $year, $month,$userId);
        $data['type'] = "summary";
        $data["summary"] = $summary;
        $pdf = PDF::loadView('admin.attendance.generate_pdf', $data);

        return $pdf->download($fileName);
    }


    public function detail($id)
    {
        $attendance = Attendance::find($id);
        $this->attendanceActivity = Attendance::userAttendanceByDate($attendance->clock_in_time->format('Y-m-d'), $attendance->clock_in_time->format('Y-m-d'), $attendance->user_id);

        $this->firstClockIn = Attendance::where(DB::raw('DATE(attendances.clock_in_time)'), $attendance->clock_in_time->format('Y-m-d'))
            ->where('user_id', $attendance->user_id)->orderBy('id', 'asc')->first();
        $this->lastClockOut = Attendance::where(DB::raw('DATE(attendances.clock_in_time)'), $attendance->clock_in_time->format('Y-m-d'))
            ->where('user_id', $attendance->user_id)->orderBy('id', 'desc')->first();

        $this->startTime = Carbon::parse($this->firstClockIn->clock_in_time)->timezone($this->global->timezone);

        if (!is_null($this->lastClockOut->clock_out_time)) {
            $this->endTime = Carbon::parse($this->lastClockOut->clock_out_time)->timezone($this->global->timezone);
        } elseif (($this->lastClockOut->clock_in_time->timezone($this->global->timezone)->format('Y-m-d') != Carbon::now()->timezone($this->global->timezone)->format('Y-m-d')) && is_null($this->lastClockOut->clock_out_time)) {
            $this->endTime = Carbon::parse($this->startTime->format('Y-m-d') . ' ' . $this->attendanceSettings->office_end_time, $this->global->timezone);
            $this->notClockedOut = true;
        } else {
            $this->notClockedOut = true;
            $this->endTime = Carbon::now()->timezone($this->global->timezone);
        }

        $this->totalTime = $this->endTime->diff($this->startTime, true)->format('%h.%i');
        $this->user_attendance = $attendance;
        return view('admin.attendance.attendance_info', $this->data);
    }

    public function mark(Request $request, $userid, $day, $month, $year)
    {
        $this->date = Carbon::createFromFormat('d-m-Y', $day . '-' . $month . '-' . $year)->format('Y-m-d');
        $this->row = Attendance::attendanceByUserDate($userid, $this->date);
        $this->clock_in = 0;
        $this->total_clock_in = Attendance::where('user_id', $userid)
            ->where(DB::raw('DATE(attendances.clock_in_time)'), '=', $this->date)
            ->whereNull('attendances.clock_out_time')->count();

        $this->userid = $userid;
        $this->type = 'add';
        return view('admin.attendance.attendance_mark', $this->data);
    }

    public function storeMark(StoreAttendance $request)
    {
        $date = Carbon::createFromFormat($this->global->date_format, $request->attendance_date)->format('Y-m-d');

        $clockIn = Carbon::createFromFormat('Y-m-d '.$this->global->time_format, $date.' '.$request->clock_in_time, $this->global->timezone);
        $clockIn->setTimezone('UTC');
        if ($request->clock_out_time != '') {
            $clockOut = Carbon::createFromFormat('Y-m-d '.$this->global->time_format, $date.' '.$request->clock_out_time, $this->global->timezone);
            $clockOut->setTimezone('UTC');

            if ($clockIn->gt($clockOut) && !is_null($clockOut)) {
                return Reply::error(__('messages.clockOutTimeError'));
            }

            $clockIn = $clockIn->toDateTimeString();
            $clockOut = $clockOut->toDateTimeString();

        } else {
            $clockOut = null;
        }

        $attendance = Attendance::where('user_id', $request->user_id)
            ->where(DB::raw('DATE(`clock_in_time`)'), "$date")
            ->whereNull('clock_out_time')
            ->first();

        $clockInCount = Attendance::getTotalUserClockIn($date, $request->user_id);

        if (!is_null($attendance)) {
            $attendance->update([
                'user_id' => $request->user_id,
                'clock_in_time' => $clockIn,
                'clock_in_ip' => $request->clock_in_ip,
                'clock_out_time' => $clockOut,
                'clock_out_ip' => $request->clock_out_ip,
                'working_from' => $request->working_from,
                'late' => ($request->has('late')) ? 'yes' : 'no',
                'half_day' => ($request->has('half_day')) ? 'yes' : 'no'
            ]);
        } else {

            // Check maximum attendance in a day
            if ($clockInCount < $this->attendanceSettings->clockin_in_day) {
                Attendance::create([
                    'user_id' => $request->user_id,
                    'clock_in_time' => $clockIn,
                    'clock_in_ip' => $request->clock_in_ip,
                    'clock_out_time' => $clockOut,
                    'clock_out_ip' => $request->clock_out_ip,
                    'working_from' => $request->working_from,
                    'late' => ($request->has('late')) ? 'yes' : 'no',
                    'half_day' => ($request->has('half_day')) ? 'yes' : 'no'
                ]);
            } else {
                return Reply::error(__('messages.maxColckIn'));
            }
        }

        return Reply::success(__('messages.attendanceSaveSuccess'));
    }

}

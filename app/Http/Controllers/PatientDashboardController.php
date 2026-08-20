<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Specialty;
use Carbon\Carbon;
use App\Models\User;

class PatientDashboardController extends Controller
{
public function index()
{
    $doctors = User::role('Doctor')->with('specialty')->get();
    return view('patient.dashboard', compact('doctors'));
}



public function data()
{
    $patientId = auth()->id();

    return response()->json([
        'citas_por_status' => [
            'pending'   => Appointment::where('patient_id',$patientId)->where('status','pending')->count(),
            'confirmed' => Appointment::where('patient_id',$patientId)->where('status','confirmed')->count(),
            'completed' => Appointment::where('patient_id',$patientId)->where('status','completed')->count(),
            'cancelled' => Appointment::where('patient_id',$patientId)->where('status','cancelled')->count(),
        ],

        // Lista de especialidades
        'specialties' => Specialty::all(['id','name']),

        // Lista de doctores con su especialidad
        'doctors' => DoctorProfile::with(['user','specialty'])->get()->map(fn($d)=>[
            'id'           => $d->user_id, // apunta al usuario doctor
            'name'         => $d->user->name . ' ' . $d->user->last_name,
            'specialty'    => $d->specialty?->name,
            'specialty_id' => $d->specialty_id,
            'photo'        => $d->photo ?? null,
        ]),

        // Próximas citas
        'proximas_citas' => Appointment::with('doctor')
            ->where('patient_id',$patientId)
            ->whereDate('appointment_date','>=',now())
            ->get()
            ->map(fn($c)=>[
                'id'              => $c->id,
                'appointment_date'=> Carbon::parse($c->appointment_date)->format('Y-m-d'),
                'start_time'      => $c->start_time,
                'end_time'        => $c->end_time,
                'reason'          => $c->reason,
                'status'          => $c->status,
                'doctor_name'     => $c->doctor?->name . ' ' . $c->doctor?->last_name,
            ]),

        // Historial de citas
        'historial' => Appointment::with('doctor')
            ->where('patient_id',$patientId)
            ->whereDate('appointment_date','<',now())
            ->get()
            ->map(fn($h)=>[
                'appointment_date'=> Carbon::parse($h->appointment_date)->format('Y-m-d'),
                'doctor_name'     => $h->doctor?->name . ' ' . $h->doctor?->last_name,
                'observaciones'   => $h->observaciones,
            ]),
    ]);
}


    public function store(Request $request)
    {
        $appointment = Appointment::create([
            'patient_id'      => auth()->id(),
            'doctor_id'       => $request->doctor_id,
            'specialty_id'    => $request->specialty_id,
            'appointment_date'=> $request->date,
            'start_time'      => $request->time,
            'end_time'        => Carbon::parse($request->time)->addMinutes(30)->format('H:i'),
            'status'          => 'pending',
            'reason'          => $request->reason ?? 'Consulta',
        ]);

        return response()->json(['success' => true, 'appointment' => $appointment]);
    }

    public function cancel($id)
    {
        $appointment = Appointment::where('id',$id)->where('patient_id',auth()->id())->firstOrFail();
        if(in_array($appointment->status,['pending','confirmed'])){
            $appointment->status = 'cancelled';
            $appointment->save();
        }
        return response()->json(['success' => true]);
    }
}

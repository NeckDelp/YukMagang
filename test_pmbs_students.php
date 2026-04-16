<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::where('email', 'pmbs@gmail.com')->first();
$teacher = \App\Models\Teacher::where('user_id', $user->id)->first();

$companyId = 9;

        $students = \App\Models\InternshipAssignment::with([
            'student.user'
        ])
            ->where('supervisor_teacher_id', $teacher->id)
            ->where('company_id', $companyId)
            ->get();

echo "Count: " . $students->count() . "\n";
echo json_encode($students->toArray(), JSON_PRETTY_PRINT);

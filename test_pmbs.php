<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::where('email', 'pmbs@gmail.com')->first();
if(!$user) { echo "User not found\n"; exit; }
echo "User ID: " . $user->id . "\n";
echo "User Role:" . $user->role . "\n";
$teacher = \App\Models\Teacher::where('user_id', $user->id)->first();
if(!$teacher) { echo "No teacher profile\n"; exit; }
echo "Teacher ID: " . $teacher->id . "\n";
$count = \App\Models\InternshipAssignment::where('supervisor_teacher_id', $teacher->id)->count();
echo "Assignments using supervisor_teacher_id: " . $count . "\n";
$count2 = \App\Models\InternshipAssignment::where('teacher_id', $teacher->id)->count();
echo "Assignments using teacher_id: " . $count2 . "\n";

<?php /** @var array $student */
use App\Core\Auth;
$canManage = Auth::hasRole('admin', 'hostel_admin');
$row = function($label,$value){ echo '<div class="flex justify-between gap-4 py-2.5 border-b border-gray-100 last:border-0"><span class="text-sm text-gray-500">'.$label.'</span><span class="text-sm font-medium text-gray-700 text-right">'.e($value ?: '—').'</span></div>'; };
?>
<div class="flex items-center justify-between mb-4">
    <a href="<?= url('/students') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back</a>
    <?php if ($canManage): ?>
        <div class="flex gap-2">
            <a href="<?= url('/students/'.$student['id'].'/statement') ?>" class="btn bg-green-600 hover:bg-green-700 text-white"><i class="fa-solid fa-file-pdf"></i>Statement</a>
            <a href="<?= url('/students/'.$student['id'].'/edit') ?>" class="btn bg-amber-500 hover:bg-amber-600 text-white"><i class="fa-solid fa-pen"></i>Edit</a>
            <?php if (Auth::hasRole('admin')): ?>
            <form method="post" action="<?= url('/students/'.$student['id'].'/delete') ?>" onsubmit="return confirm('Delete this student?')">
                <?= csrf_field() ?>
                <button class="btn bg-red-500 hover:bg-red-600 text-white"><i class="fa-solid fa-trash"></i>Delete</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="ui-card overflow-hidden text-center" data-reveal="0">
        <div class="h-20 bg-gradient-to-br from-primary-600 to-primary-900 relative">
            <div aria-hidden="true" class="absolute inset-0 opacity-[0.08]" style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:14px 14px;"></div>
        </div>
        <div class="relative z-10 px-6 pb-6 -mt-12">
            <div class="w-24 h-24 rounded-full overflow-hidden bg-primary-100 text-primary-600 text-4xl font-bold flex items-center justify-center mx-auto ring-4 ring-white shadow-pop">
                <?php if (!empty($student['photo'])): ?>
                    <img src="<?= url('/uploads/'.$student['photo']) ?>" alt="<?= e($student['full_name']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <?= e(strtoupper(substr($student['full_name'],0,1))) ?>
                <?php endif; ?>
            </div>
            <h2 class="mt-3 text-lg font-display font-bold text-gray-800"><?= e($student['full_name']) ?></h2>
            <p class="text-sm text-gray-500"><?= e($student['student_id']) ?></p>
            <div class="mt-2"><?= status_badge($student['status']) ?></div>
        </div>
    </div>

    <div class="lg:col-span-2 ui-card p-6" data-reveal="1">
        <h3 class="font-display font-bold text-gray-800 mb-3 flex items-center gap-2"><i class="fa-solid fa-circle-info text-primary-500 text-sm"></i>Profile Details</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8">
            <div>
                <?php $row('Gender', ucfirst($student['gender'])); $row('Date of Birth', datef($student['date_of_birth'])); $row('Nationality', $student['nationality']); $row('Programme', $student['programme']); $row('Department', $student['department']); $row('Level', $student['level']); ?>
            </div>
            <div>
                <?php $row('Phone', $student['phone']); $row('Email', $student['email']); $row('Address', $student['address']); $row('Guardian', $student['guardian_name']); $row('Guardian Phone', $student['guardian_phone']); $row('Blood Group', $student['blood_group']); $row('Emergency', $student['emergency_contact']); ?>
            </div>
        </div>
    </div>
</div>

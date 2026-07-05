<?php /** @var ?array $student */
/** @var ?array $hostels list of hostels for the super admin's selector, or null when hostel-bound */
$isEdit = $student !== null;
$action = $isEdit ? url('/students/' . $student['id']) : url('/students');
$hostels = $hostels ?? null;
function val(?array $s, string $k): string { return e($s[$k] ?? old($k)); }
?>
<a href="<?= url('/students') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to students</a>

<form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="ui-card p-6 mt-3 space-y-7 reveal">
    <?= csrf_field() ?>

    <section>
        <h3 class="font-display font-bold text-gray-800 border-b border-gray-100 pb-2.5 mb-4 flex items-center gap-2"><i class="fa-solid fa-image text-primary-500 text-sm"></i>Photo</h3>
        <div class="flex items-center gap-4">
            <?php $hasPhoto = $isEdit && !empty($student['photo']); ?>
            <div class="w-20 h-20 rounded-full overflow-hidden bg-primary-100 text-primary-600 flex items-center justify-center text-2xl font-bold ring-2 ring-primary-200">
                <?php if ($hasPhoto): ?>
                    <img src="<?= url('/uploads/'.$student['photo']) ?>" alt="photo" class="w-full h-full object-cover">
                <?php else: ?>
                    <?= e(strtoupper(substr($student['full_name'] ?? 'S', 0, 1))) ?>
                <?php endif; ?>
            </div>
            <div>
                <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="block text-sm text-gray-600">
                <p class="text-xs text-gray-400 mt-1">JPG, PNG or WebP, up to 2 MB.</p>
            </div>
        </div>
    </section>

    <section>
        <h3 class="font-display font-bold text-gray-800 border-b border-gray-100 pb-2.5 mb-4 flex items-center gap-2"><i class="fa-solid fa-id-card text-primary-500 text-sm"></i>Basic Information</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php
            $text = function($name,$label,$req=false) use ($student){
                echo '<div><label class="block text-sm font-medium text-gray-600 mb-1.5">'.$label.($req?' <span class="text-red-500">*</span>':'').'</label>';
                echo '<input name="'.$name.'" value="'.val($student,$name).'" '.($req?'required':'').' class="ui-input"></div>';
            };
            $text('student_id','Student ID',true);
            $text('full_name','Full Name',true);
            ?>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Gender</label>
                <select name="gender" class="ui-input">
                    <?php foreach (['male','female','other'] as $g): ?>
                        <option value="<?= $g ?>" <?= ($student['gender']??'')===$g?'selected':'' ?>><?= ucfirst($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Date of Birth</label>
                <input type="date" name="date_of_birth" value="<?= val($student,'date_of_birth') ?>" class="ui-input">
            </div>
            <?php $text('nationality','Nationality'); $text('programme','Programme'); $text('department','Department'); $text('level','Level'); ?>
            <?php if ($hostels !== null): ?>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Hostel</label>
                <select name="hostel_id" class="ui-input">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($hostels as $h): ?>
                        <option value="<?= (int)$h['id'] ?>" <?= (string)($student['hostel_id']??'')===(string)$h['id']?'selected':'' ?>><?= e($h['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section>
        <h3 class="font-display font-bold text-gray-800 border-b border-gray-100 pb-2.5 mb-4 flex items-center gap-2"><i class="fa-solid fa-address-book text-primary-500 text-sm"></i>Contact</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php $text('phone','Phone'); $text('email','Email'); $text('address','Address'); ?>
        </div>
    </section>

    <section>
        <h3 class="font-display font-bold text-gray-800 border-b border-gray-100 pb-2.5 mb-4 flex items-center gap-2"><i class="fa-solid fa-kit-medical text-primary-500 text-sm"></i>Guardian &amp; Medical</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php
            $text('guardian_name','Guardian Name');
            $text('guardian_phone','Guardian Phone');
            $text('guardian_relationship','Relationship');
            $text('blood_group','Blood Group');
            $text('allergies','Allergies');
            $text('emergency_contact','Emergency Contact');
            ?>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Status</label>
                <select name="status" class="ui-input">
                    <?php foreach (['active','suspended','graduated','inactive'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($student['status']??'active')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <div class="flex justify-end items-center gap-3 pt-3 border-t border-gray-100">
        <a href="<?= url('/students') ?>" class="btn btn-ghost border-transparent hover:bg-gray-100">Cancel</a>
        <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i><?= $isEdit?'Update':'Save' ?> Student</button>
    </div>
</form>

<a href="<?= url('/students') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to students</a>

<div class="ui-card p-6 mt-3 max-w-2xl reveal">
    <h3 class="font-display font-bold text-gray-800 mb-2 flex items-center gap-2"><i class="fa-solid fa-file-csv text-primary-500 text-sm"></i>Bulk Import Students (CSV)</h3>
    <p class="text-sm text-gray-500 mb-4">Upload a <code class="bg-gray-100 px-1 py-0.5 rounded">.csv</code> file whose first row is a header. Recognised columns
        (any order): <code class="bg-gray-100 px-1 py-0.5 rounded">student_id</code>, <code class="bg-gray-100 px-1 py-0.5 rounded">full_name</code>,
        <code class="bg-gray-100 px-1 py-0.5 rounded">gender</code>, <code class="bg-gray-100 px-1 py-0.5 rounded">programme</code>, <code class="bg-gray-100 px-1 py-0.5 rounded">department</code>, <code class="bg-gray-100 px-1 py-0.5 rounded">level</code>,
        <code class="bg-gray-100 px-1 py-0.5 rounded">phone</code>, <code class="bg-gray-100 px-1 py-0.5 rounded">email</code>. Rows with an existing Student ID are skipped.</p>

    <form method="post" action="<?= url('/students/import') ?>" enctype="multipart/form-data" class="space-y-4">
        <?= csrf_field() ?>
        <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-primary-300 hover:bg-primary-50/30 transition">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-primary-50 text-primary-500 items-center justify-center mb-2"><i class="fa-solid fa-cloud-arrow-up text-2xl"></i></span>
            <input type="file" name="file" accept=".csv,text/csv" required class="block w-full text-sm text-gray-600 mt-2 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary-50 file:text-primary-700 file:font-medium hover:file:bg-primary-100 file:cursor-pointer">
        </div>
        <div class="flex justify-end items-center gap-3">
            <a href="<?= url('/students') ?>" class="btn btn-ghost border-transparent hover:bg-gray-100">Cancel</a>
            <button class="btn btn-primary"><i class="fa-solid fa-upload"></i> Import</button>
        </div>
    </form>

    <div class="mt-5 pt-4 border-t border-gray-100">
        <p class="text-xs text-gray-400 mb-1">Example CSV content:</p>
        <pre class="bg-gray-50 rounded-lg p-3 text-xs text-gray-600 overflow-x-auto">student_id,full_name,gender,programme,level,phone,email
STU-1001,Yaw Mensah,male,BSc Engineering,200,0240000001,yaw@example.com
STU-1002,Akua Boateng,female,BA Economics,100,0240000002,akua@example.com</pre>
    </div>
</div>

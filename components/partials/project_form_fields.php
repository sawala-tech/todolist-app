<?php
/**
 * components/partials/project_form_fields.php
 * Shared form fields for create/edit project modals.
 * Variables: $editMode (bool, optional)
 */
$editMode = $editMode ?? false;
$idSuffix = $editMode ? 'Edit' : 'Create';
?>
<div class="flex flex-col space-y-1">
    <label class="text-sm font-semibold">Nama Project <span class="text-red-500">*</span></label>
    <input type="text" name="name" required
           id="project<?= $idSuffix ?>Name"
           class="h-10 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
           placeholder="Nama project">
</div>
<div class="flex flex-col space-y-1">
    <label class="text-sm font-semibold">Deskripsi</label>
    <textarea name="description" rows="3"
              id="project<?= $idSuffix ?>Desc"
              class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
              placeholder="Deskripsi singkat project"></textarea>
</div>
<div class="grid grid-cols-2 gap-4">
    <div class="flex flex-col space-y-1">
        <label class="text-sm font-semibold">Tanggal Mulai</label>
        <input type="date" name="start_date"
               id="project<?= $idSuffix ?>StartDate"
               class="h-10 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm">
    </div>
    <div class="flex flex-col space-y-1">
        <label class="text-sm font-semibold">Deadline</label>
        <input type="date" name="deadline"
               id="project<?= $idSuffix ?>Deadline"
               class="h-10 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm">
    </div>
</div>
<div class="flex flex-col space-y-1">
    <label class="text-sm font-semibold">Status</label>
    <select name="status"
            id="project<?= $idSuffix ?>Status"
            class="h-10 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm bg-white">
        <option value="draft">Draft</option>
        <option value="active">Active</option>
        <option value="archived">Archived</option>
    </select>
</div>

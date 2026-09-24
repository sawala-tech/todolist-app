<?php
/**
 * assets/helpers/ui_helpers.php
 *
 * UI utility helpers: status labels (Indonesia), badge HTML, dll.
 */

// ────────────────────────────────────────────────────────────────
// Status labels — copywriting Indonesia
// ────────────────────────────────────────────────────────────────

const TASK_STATUS_LABELS = [
    'open'        => 'Belum Dimulai',
    'in_progress' => 'Sedang Dikerjakan',
    'review'      => 'Menunggu Review',
    'revision'    => 'Perlu Revisi',
    'done'        => 'Selesai',
];

const TASK_STATUS_CLASSES = [
    'open'        => 'bg-gray-100 text-gray-600',
    'in_progress' => 'bg-blue-100 text-blue-700',
    'review'      => 'bg-yellow-100 text-yellow-700',
    'revision'    => 'bg-red-100 text-red-700',
    'done'        => 'bg-emerald-100 text-emerald-700',
];

const TASK_PRIORITY_LABELS = [
    'low'    => 'Rendah',
    'medium' => 'Sedang',
    'high'   => 'Tinggi',
];

const TASK_PRIORITY_CLASSES = [
    'low'    => 'bg-gray-100 text-gray-600',
    'medium' => 'bg-orange-100 text-orange-700',
    'high'   => 'bg-red-100 text-red-700',
];

const USER_STATUS_LABELS = [
    'active'    => 'Aktif',
    'pending'   => 'Menunggu Aktivasi',
    'suspended' => 'Ditangguhkan',
];

const USER_STATUS_CLASSES = [
    'active'    => 'bg-emerald-100 text-emerald-700',
    'pending'   => 'bg-yellow-100 text-yellow-700',
    'suspended' => 'bg-red-100 text-red-700',
];

const PROJECT_STATUS_LABELS = [
    'draft'    => 'Draft',
    'active'   => 'Aktif',
    'archived' => 'Diarsipkan',
];

const PROJECT_STATUS_CLASSES = [
    'draft'    => 'bg-gray-100 text-gray-600',
    'active'   => 'bg-emerald-100 text-emerald-700',
    'archived' => 'bg-orange-100 text-orange-700',
];

const INVITATION_STATUS_LABELS = [
    'pending'       => 'Terkirim',
    'expired'       => 'Kadaluarsa',
    'revoked'       => 'Dicabut',
    'used'          => 'Sudah Digunakan',
    'no_invitation' => 'Belum Diundang',
];

const INVITATION_STATUS_CLASSES = [
    'pending'       => 'bg-blue-100 text-blue-700',
    'expired'       => 'bg-orange-100 text-orange-700',
    'revoked'       => 'bg-red-100 text-red-700',
    'used'          => 'bg-gray-100 text-gray-400',
    'no_invitation' => 'bg-gray-100 text-gray-400',
];

const ROLE_LABELS = [
    'admin' => 'Admin',
    'user'  => 'Pengguna',
];

const ROLE_CLASSES = [
    'admin' => 'bg-violet-100 text-violet-700',
    'user'  => 'bg-slate-100 text-slate-600',
];

// ────────────────────────────────────────────────────────────────
// Render helpers
// ────────────────────────────────────────────────────────────────

/**
 * Render badge HTML untuk status.
 *
 * @param  string  $value   e.g. 'in_progress'
 * @param  array   $labels  Mapping value → label
 * @param  array   $classes Mapping value → Tailwind classes
 * @param  string  $size    'sm' | 'md'
 */
function statusBadge(string $value, array $labels, array $classes, string $size = 'sm'): string
{
    $label = $labels[$value]  ?? $value;
    $cls   = $classes[$value] ?? 'bg-gray-100 text-gray-600';
    $pad   = $size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm';
    return '<span class="inline-flex items-center rounded-full font-medium ' . $pad . ' ' . $cls . '">'
         . htmlspecialchars($label)
         . '</span>';
}

function taskStatusBadge(string $status, string $size = 'sm'): string
{
    return statusBadge($status, TASK_STATUS_LABELS, TASK_STATUS_CLASSES, $size);
}

function taskPriorityBadge(string $priority, string $size = 'sm'): string
{
    return statusBadge($priority, TASK_PRIORITY_LABELS, TASK_PRIORITY_CLASSES, $size);
}

function userStatusBadge(string $status, string $size = 'sm'): string
{
    return statusBadge($status, USER_STATUS_LABELS, USER_STATUS_CLASSES, $size);
}

function projectStatusBadge(string $status, string $size = 'sm'): string
{
    return statusBadge($status, PROJECT_STATUS_LABELS, PROJECT_STATUS_CLASSES, $size);
}

function invitationStatusBadge(string $status, string $size = 'sm'): string
{
    return statusBadge($status, INVITATION_STATUS_LABELS, INVITATION_STATUS_CLASSES, $size);
}

function roleBadge(string $role, string $size = 'sm'): string
{
    return statusBadge($role, ROLE_LABELS, ROLE_CLASSES, $size);
}

/**
 * Render action button HTML.
 */
function actionBtn(string $label, string $href = '', string $variant = 'default', string $extra = ''): string
{
    $variants = [
        'primary' => 'bg-blue-600 hover:bg-blue-700 text-white',
        'success' => 'bg-emerald-500 hover:bg-emerald-600 text-white',
        'danger'  => 'bg-red-500 hover:bg-red-600 text-white',
        'default' => 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50',
        'ghost'   => 'text-blue-600 hover:bg-blue-50',
    ];
    $cls = $variants[$variant] ?? $variants['default'];
    if ($href) {
        return "<a href=\"{$href}\" class=\"inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg {$cls} {$extra}\">{$label}</a>";
    }
    return "<button type=\"button\" class=\"inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg {$cls} {$extra}\" {$extra}>{$label}</button>";
}

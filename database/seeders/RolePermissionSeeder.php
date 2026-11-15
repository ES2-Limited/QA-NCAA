<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define all system permissions
        $permissions = [
            // Process management
            'processes.view' => 'View process templates and checklist questionnaires',
            'processes.create' => 'Create and draft new process templates and checklists',
            'processes.edit' => 'Update existing process templates and checklists',
            'processes.delete' => 'Delete process templates',

            // Audit scheduling & management
            'audits.view' => 'View general audit register',
            'audits.schedule' => 'Schedule, create, and configure new audits',
            'audits.update' => 'Modify scheduled audit parameters',
            'audits.delete' => 'Cancel or delete audits',

            // Field execution
            'audits.conduct' => 'Perform on-site audit inspection and submit responses in the field',

            // Review & workflow approvals
            'audits.review' => 'Conduct technical quality reviews of submitted audits',
            'approvals.endorse' => 'Head of Audit tier endorsement',
            'approvals.final' => 'Directorate tier final certification',

            // NCRs & CAPs
            'ncrs.view' => 'View Non-Conformance Reports',
            'ncrs.raise' => 'Raise and manage non-conformance findings during audits',
            'ncrs.edit' => 'Edit non-conformance findings',
            'ncrs.delete' => 'Delete non-conformance findings',
            'caps.submit' => 'Submit Corrective Action Plans as auditee',
            'caps.review' => 'Review, accept, or reject Corrective Action Plans',

            // Document library
            'documents.view' => 'Browse and search document library',
            'documents.upload' => 'Upload compliance manuals and regulatory documents',
            'documents.download' => 'Download stored compliance documents',
            'documents.delete' => 'Remove compliance documents',

            // Admin & system governance
            'settings.manage' => 'Configure system branding, audit rules, and lookups',
            'workflow.manage' => 'Manage multi-stage approval workflow steps',
            'users.manage' => 'Manage user accounts, units, and role permissions',
            'roles.manage' => 'Manage roles and assign permissions',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // 2. Define the 7 baseline roles and their permissions
        $rolePermissions = [
            'system_admin' => array_keys($permissions), // System admin has full access

            'lead_auditor' => [
                'processes.view',
                'processes.create',
                'processes.edit',
                'audits.view',
                'audits.schedule',
                'audits.update',
                'ncrs.view',
                'ncrs.raise',
                'ncrs.edit',
                'caps.review',
                'documents.view',
                'documents.upload',
                'documents.download',
            ],

            'field_auditor' => [
                'audits.view',
                'audits.conduct',
                'ncrs.view',
                'ncrs.raise',
            ],

            'reviewer' => [
                'audits.view',
                'audits.review',
                'ncrs.view',
                'caps.review',
                'documents.view',
                'documents.download',
            ],

            'head_of_audit' => [
                'processes.view',
                'audits.view',
                'approvals.endorse',
                'ncrs.view',
                'documents.view',
                'documents.download',
            ],

            'director' => [
                'processes.view',
                'audits.view',
                'approvals.final',
                'ncrs.view',
                'documents.view',
                'documents.download',
            ],

            'auditee' => [
                'ncrs.view',
                'caps.submit',
            ],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

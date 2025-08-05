<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Ramsey\Uuid\Uuid;

class UpdatePrivilegesTables extends Migration
{
    protected Connection $db;

    public $timestamps = true; // phpcs:ignore

    // Departments permissions (new)
    protected array $new_departments_permissions = [
        'dept.admin' => 'No restrictions',
        'dept.add' => 'Add Departments + allow view, edit, delete',
        'dept.view' => 'View Departments',
        'dept.edit' => 'Edit Departments',
        'dept.delete' => 'Delete Departments - Dangerous permission',
    ];

    // New extra permissions
    protected array $new_user_permissions = [
        'user.type.staff' => 'User Type: STAFF',
        'user.type.admin' => 'User Type: Administrator',
    ];

    // Category name => Description
    protected array $list_of_privileges_categories = [
        'Administrative' => 'Special Administrative permissions',
        'Certification' => 'Module Certifications',
        'Critter' => 'Module Critters',
        'Department' => 'Module Departments',
        'FAQ' => 'Module FAQ',
        'Global' => 'Global Permisisons that affects the website in general',
        'Goodie' => 'Module Goodies',
        'Location' => 'Module Locations',
        'Meeting' => 'Module Meetings',
        'Message' => 'Module Messages',
        'News' => 'Module News',
        'Question' => 'Module Questions',
        'Shift' => 'Module Shifts',
        'Staff' => 'Module Staffs',
        'User' => 'User related permissions',
        'Others' => 'Permissions without a proper category',
    ];

    // Mapping between privileges and privileges category
    protected array $categories_map = [
        'Global' => [
            'api',
            'atom',
            'ical',
            'login',
            'logout',
            'start',
            'register',
        ],
        'Administrative' => [
            'admin_groups',
            'admin_log',
            'config.edit',
            'logs.all',
            'schedule.import',
            'user.type.admin',
        ],
        'Location' => [
            'admin_locations',
            'view_locations',
        ],
        'Meeting' => ['user_meetings'],
        'Message' => ['user_messages'],
        'Critter' => [
            'admin_active',
            'admin_angel_types',
            'admin_arrive',
            'admin_free',
            'admin_user',
            'admin_user_angeltypes',
            'angeltypes',
            'user_angeltypes',
        ],
        'Question' => [
            'question.add',
            'question.edit',
        ],
        'Certification' => [
            'user.drive.edit',
            'user.ifsg.edit',
        ],
        'FAQ' => [
            'faq.edit',
            'faq.view',
        ],
        'News' => [
            'admin_news',
            'news',
            'news_comments',
            'news.highlight',
        ],
        'Shift' => [
            'admin_shifts',
            'admin_user_worklog',
            'shifts_json_export',
            'shifttypes.edit',
            'shifttypes.view',
            'user_myshifts',
            'user_shifts',
            'user_shifts_admin',
        ],
        'User' => [
            'user_settings',
            'user.info.edit',
            'user.info.show',
            'user.nick.edit',
            'user.fa.edit',
            'users.arrive.list',
        ],
        'Staff' => [
            'user.type.internal_staff',
            'user.type.staff',
        ],
        'Goodie' => [
            'voucher.edit',
            'user.goodie.edit',
        ],
        'Department' => [
            'dept.admin',
            'dept.add',
            'dept.view',
            'dept.edit',
            'dept.delete',
        ],
    ];

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {

        // Add new Table
        $this->schema->create('privileges_category', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // $table->timestamps();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();

            $table->index('uuid');
        });

        // Batch insert in privileges_category
        $this->insertPrivilegesCategoryArray(list_array: $this->list_of_privileges_categories);

        // Add Column to privileges
        $this->schema->table('privileges', function (Blueprint $table): void {
            $table->foreignId('fk_privileges_category_id')
                ->nullable()
                ->after(column: 'id')
                ->constrained(table: 'privileges_category')
                ->onDelete(action: 'set null');
        });

        // Department Permissions + User Type
        // Insert Department
        foreach ($this->new_departments_permissions as $permission_name => $permission_description) {
            $this->insertPrivilege(
                name: $permission_name,
                description: $permission_description,
                category_id: null
            );
        }
        // Insert User Type
        foreach ($this->new_user_permissions as $permission_name => $permission_description) {
            $this->insertPrivilege(
                name: $permission_name,
                description: $permission_description,
                category_id: null // Next logic will assign the value
            );
        }

        // Now we need to normalize the Permissions table to have the category set
        foreach ($this->categories_map as $category => $permissions) {
            // Get the ID of the group
            $category_id = $this->getPrivilegesCategoryIdByName(name: $category);

            // Now, for each permission, we set the permission category ID
            foreach ($permissions as $permission) {
                $this->updatePrivilegeWithCategoryId(
                    name: $permission,
                    category_id: $category_id
                );
            }
        }

        // Let's add the Admin group
        $this->insertGroup(id: 1, name: 'Admin', slug: 'admin');
        // We need to add all permissions to the group
        $all_permissions = $this->db->table(table: 'privileges')
            ->get();

        foreach ($all_permissions as $permission) {
            // print_r($permission . PHP_EOL);
            // print_r(gettype($permission->id) . PHP_EOL);

            $this->db->table(table: 'group_privileges')
                ->insert([
                    'group_id' => 1,
                    'privilege_id' => $permission->id,
                ]);
        }
    }

    public function down(): void
    {
        // Remove the admin group_privileges first
        $this->db->table(table: 'group_privileges')
            ->where(column: ['group_id' => 1])
            ->delete();

        $this->db->table(table: 'groups')
            ->where(column: ['id' => 1])
            ->delete();

        // ********************************************************************
        // DROP slug column from Departments
        // ********************************************************************
        $this->schema->table('privileges', function (Blueprint $table): void {
            $table->dropForeign(['fk_privileges_category_id']);
            $table->dropColumn('fk_privileges_category_id');
        });

        // Clean the permissions added - Department
        foreach ($this->new_departments_permissions as $department_permission => $department_description) {
            $this->db->table(table: 'privileges')
                ->where(column: ['name' => $department_permission])
                ->delete();
        }

        // Clean the permissions added - Department
        foreach ($this->new_user_permissions as $user_permission => $user_description) {
            $this->db->table('privileges')
                ->where(['name' => $user_permission])
                ->delete();
        }

        $this->schema->dropIfExists('privileges_category');
    }

    // #################################################################
    // Support Functions
    // #################################################################
    // -----------------------------------------------------------------
    // Table: Privileges
    // -----------------------------------------------------------------
    protected function insertPrivilege(string $name, ?string $description, ?int $category_id): void
    {
        $this->db->table(table: 'privileges')
            ->insert([
                'name' => $name,
                'description' => $description,
                'fk_privileges_category_id' => $category_id,
            ]);
    }

    protected function updatePrivilegeWithCategoryId(string $name, int $category_id): void
    {
        $this->db->table('privileges')
            ->where('name', $name)
            ->update([
                'fk_privileges_category_id' => $category_id,
            ]);
    }

    // -----------------------------------------------------------------
    // Table: Group
    // -----------------------------------------------------------------
    /**
     * Inserts a group into the database and assigns the given privileges to it.
     *
     * @param int $id The ID of the group - MANDATORY (legacy reasons)
     * @param string $name The name of the group
     *
     */
    protected function insertGroup(int $id, string $name, string $slug): void
    {
        $this->db->table('groups')
            ->insert([
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
            ]);
    }

    protected function getGroupIdByName(string $name): int
    {
        return $this->db->table('groups')
            ->where('name', $name)
            ->get(['id'])
            ->first()->id;
    }

    // -----------------------------------------------------------------
    // Table: Privileges Category
    // -----------------------------------------------------------------
    /**
     * Insert a new Privilege Category
     *
     * @param string $name Category Name.
     * @param string $description Description of the category.
     */
    protected function insertPrivilegesCategory(string $name, string $description): void
    {
        $this->db->table('privileges_category')
            ->insertOrIgnore([
                'uuid' => Uuid::uuid4(),
                'name' => $name,
                'description' => $description,
                // 'created_at' => DB::raw('CURRENT_TIMESTAMP'),
                // 'updated_at' => DB::raw('CURRENT_TIMESTAMP')
            ]);
    }

    /**
     * Batch insert several Privileges Caterogy
     *
     * @param array $list_array Complete list with name/description to be inserted
     */
    protected function insertPrivilegesCategoryArray(array $list_array): void
    {
        foreach ($list_array as $name => $description) {
            $this->insertPrivilegesCategory(name: $name, description: $description);
        }
    }

    protected function getPrivilegesCategoryIdByName(string $name): int
    {
        return $this->db->table('privileges_category')
            ->where('name', $name)
            ->get(['id'])
            ->first()->id;
    }
}

<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Carbon\Carbon;
use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class CreateGoodiesItems extends Migration
{
    use Reference;

    protected Connection $db;

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    /**
     * Run the migration
     */
    public function up(): void
    {
        $this->schema->create('goodiesv2_items', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('category_id')->unsigned();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->integer('required_hours')->default(0);
            $table->integer('initial_quantity')->default(0);
            $table->integer('current_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('category_id')
                ->references('id')->on('goodiesv2_categories')
                ->onDelete('cascade');

            $table->index(['category_id']);
            $table->index(['is_active']);
            $table->index(['required_hours']);
            $table->index(['sort_order']);
            $table->index(['current_quantity']);
        });

        // Insert sample items for each category
        $accessoriesCategoryId = $this->db->table('goodiesv2_categories')
            ->where('name', 'Accessories')
            ->value('id');

        $specialCategoryId = $this->db->table('goodiesv2_categories')
            ->where('name', 'Special Items')
            ->value('id');

        $this->db->table('goodiesv2_items')->insert([
            // // Clothing items
            // [
            //     'category_id' => $clothingCategoryId,
            //     'name' => 'EF29 Staff T-Shirt',
            //     'description' => 'Official Eurofurence 29 staff t-shirt',
            //     'required_hours' => 12,
            //     'initial_quantity' => 500,
            //     'current_quantity' => 500,
            //     'is_active' => true,
            //     'sort_order' => 10,
            //     'created_at' => Carbon::now(),
            //     'updated_at' => Carbon::now(),
            // ],
            // [
            //     'category_id' => $clothingCategoryId,
            //     'name' => 'EF29 Staff Hoodie',
            //     'description' => 'Premium staff hoodie for long-term volunteers',
            //     'required_hours' => 24,
            //     'initial_quantity' => 200,
            //     'current_quantity' => 200,
            //     'is_active' => true,
            //     'sort_order' => 20,
            //     'created_at' => Carbon::now(),
            //     'updated_at' => Carbon::now(),
            // ],
            // Accessories
            // Group 10
            [
                'category_id' => $accessoriesCategoryId,
                'name' => 'Critter Ribbon',
                'description' => 'Official Critter identification Ribbon',
                'required_hours' => 0,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 11,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'category_id' => $accessoriesCategoryId,
                'name' => 'Volunteer Pin',
                'description' => 'EF29 Volunteer Pin',
                'required_hours' => 2,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 12,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'category_id' => $accessoriesCategoryId,
                'name' => 'Sticker I',
                'description' => 'Sticker',
                'required_hours' => 3,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 13,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'category_id' => $accessoriesCategoryId,
                'name' => 'Sticker II',
                'description' => 'Sticker',
                'required_hours' => 7,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 14,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // [
            //     'category_id' => $accessoriesCategoryId,
            //     'name' => 'Staff Badge',
            //     'description' => 'Official staff identification badge',
            //     'required_hours' => 6,
            //     'initial_quantity' => 800,
            //     'current_quantity' => 800,
            //     'is_active' => true,
            //     'sort_order' => 10,
            //     'created_at' => Carbon::now(),
            //     'updated_at' => Carbon::now(),
            // ],
            // [
            //     'category_id' => $accessoriesCategoryId,
            //     'name' => 'EF29 Sticker Pack',
            //     'description' => 'Collection of Eurofurence 29 themed stickers',
            //     'required_hours' => 3,
            //     'initial_quantity' => 1000,
            //     'current_quantity' => 1000,
            //     'is_active' => true,
            //     'sort_order' => 20,
            //     'created_at' => Carbon::now(),
            //     'updated_at' => Carbon::now(),
            // ],
            // // Food & Drinks
            // [
            //     'category_id' => $foodCategoryId,
            //     'name' => 'Staff Meal Voucher',
            //     'description' => 'Free meal voucher for staff cafeteria',
            //     'required_hours' => 4,
            //     'initial_quantity' => 2000,
            //     'current_quantity' => 2000,
            //     'is_active' => true,
            //     'sort_order' => 10,
            //     'created_at' => Carbon::now(),
            //     'updated_at' => Carbon::now(),
            // ],
            // [
            //     'category_id' => $foodCategoryId,
            //     'name' => 'Staff Coffee Card',
            //     'description' => 'Prepaid card for coffee and beverages',
            //     'required_hours' => 8,
            //     'initial_quantity' => 300,
            //     'current_quantity' => 300,
            //     'is_active' => true,
            //     'sort_order' => 20,
            //     'created_at' => Carbon::now(),
            //     'updated_at' => Carbon::now(),
            // ],
            // Special Items
            // Group 20
            [
                'category_id' => $specialCategoryId,
                'name' => 'Art Raffle Ticket: Level 1',
                'description' => 'Digital Ticket for the Raffle',
                'required_hours' => 4,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 21,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'category_id' => $specialCategoryId,
                'name' => 'Art Raffle Ticket: Level 2',
                'description' => 'Digital Ticket for the Raffle',
                'required_hours' => 7,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 22,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'category_id' => $specialCategoryId,
                'name' => 'Art Raffle Ticket: Level 3',
                'description' => 'Digital Ticket for the Raffle',
                'required_hours' => 16,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 23,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'category_id' => $specialCategoryId,
                'name' => 'Art Raffle Ticket: Level 4',
                'description' => 'Digital Ticket for the Raffle',
                'required_hours' => 20,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 24,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // Group 30
            [
                'category_id' => $specialCategoryId,
                'name' => 'Priority Access Ticket',
                'description' => 'Priority Access Ticket',
                'required_hours' => 6,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 31,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'category_id' => $specialCategoryId,
                'name' => 'Priority Access Ticket',
                'description' => 'Priority Access Ticket',
                'required_hours' => 12,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 32,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'category_id' => $specialCategoryId,
                'name' => 'Priority Access Ticket',
                'description' => 'Priority Access Ticket',
                'required_hours' => 18,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 33,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // Group 40
            [
                'category_id' => $specialCategoryId,
                'name' => 'Bookmark',
                'description' => 'EF Bookmark',
                'required_hours' => 10,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 41,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'category_id' => $specialCategoryId,
                'name' => 'Postcard',
                'description' => 'EF Postcard',
                'required_hours' => 14,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'is_active' => true,
                'sort_order' => 42,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // [
            //     'category_id' => $specialCategoryId,
            //     'name' => 'EF29 Limited Edition Pin',
            //     'description' => 'Exclusive collectible pin for dedicated staff',
            //     'required_hours' => 30,
            //     'initial_quantity' => 100,
            //     'current_quantity' => 100,
            //     'is_active' => true,
            //     'sort_order' => 10,
            //     'created_at' => Carbon::now(),
            //     'updated_at' => Carbon::now(),
            // ],
            // [
            //     'category_id' => $specialCategoryId,
            //     'name' => 'Staff Appreciation Certificate',
            //     'description' => 'Personalized certificate of appreciation',
            //     'required_hours' => 20,
            //     'initial_quantity' => 500,
            //     'current_quantity' => 500,
            //     'is_active' => true,
            //     'sort_order' => 20,
            //     'created_at' => Carbon::now(),
            //     'updated_at' => Carbon::now(),
            // ],
        ]);
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->dropIfExists('goodiesv2_items');
    }
}

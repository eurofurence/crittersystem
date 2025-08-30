<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Controllers\HasUserNotifications;
use Engelsystem\Controllers\NotificationType;
use Engelsystem\Helpers\BackstagePermissionHelper;
use Engelsystem\Http\Exceptions\HttpForbidden;
use Engelsystem\Http\Redirector;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Services\GoodiesService;
use Engelsystem\Services\GoodiesDistributionService;
use Engelsystem\Services\CertificationService;
use Psr\Log\LoggerInterface;
use Carbon\Carbon;

class BackstageGoodiesController extends BaseController
{
    use HasUserNotifications;

    /** @var array<string> */
    protected array $permissions = [
//        'backstage.goodies.view',
//        'backstage.goodies.admin',
//        'backstage.admin',
    ];

    public function __construct(
        protected LoggerInterface $log,
        protected GoodiesService $goodiesService,
        protected GoodiesDistributionService $distributionService,
        protected CertificationService $certificationService,
        protected Response $response,
        protected Redirector $redirect
    ) {
    }

    // ========================================
    // CATEGORIES MANAGEMENT
    // ========================================

    /**
     * Display all goodies categories.
     */
    public function categoriesIndex(Request $request): Response
    {
        $this->checkGoodiesPermission('view');

        try {
            $categories = $this->goodiesService->getAllCategories(['active_only' => false]);

            return $this->response->withView(
                'backstage/goodies/categories/index',
                [
                    'user' => auth()->user(),
                    'categories' => $categories,
                    'can_edit' => BackstagePermissionHelper::canAdminGoodies(),
                //                    'can_edit' => true,
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading goodies categories', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('backstage.goodies.categories.error: ' . $e->getMessage(), NotificationType::ERROR); // phpcs:ignore
            return $this->redirect->to('/admin/backstage');
        }
    }

    /**
     * Show form for creating a new category.
     */
    public function createCategory(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        return $this->response->withView(
            'backstage/goodies/categories/create',
            [
                'user' => auth()->user(),
                'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
            ]
        );
    }

    /**
     * Store a newly created category.
     */
    public function storeCategory(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $data = [
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'is_active' => $request->get('is_active'),
            'display_order' => $request->get('display_order'),
        ];

        try {
            // Properly cast the data types
            $data['is_active'] = (bool) $request->get('is_active');
            $data['display_order'] = (int) $request->get('display_order', 0);

            $category = $this->goodiesService->createCategory($data);

            $this->log->info('Category created', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'category_id' => $category->id,
                'category_uuid' => $category->uuid,
                'category_name' => $category->name,
            ]);

            $this->addNotification('backstage.goodies.categories.created: Category created successfully', NotificationType::MESSAGE); // phpcs:ignore
            return $this->redirect->to('/admin/backstage/goodies/categories');
        } catch (\Exception $e) {
            $this->log->error('Error creating category', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            $this->addNotification('backstage.goodies.categories.create_error', NotificationType::ERROR);
            return $this->redirect->back();
        }
    }

    /**
     * Display the specified category.
     */
    public function showCategory(Request $request): Response
    {
        $this->checkGoodiesPermission('view');

        $uuid = $request->getAttribute('uuid');

        try {
            $category = $this->goodiesService->getCategoryByUuid($uuid);

            if (!$category) {
                $this->addNotification('backstage.goodies.categories.not_found: Category not found', NotificationType::ERROR); // phpcs:ignore
                return $this->redirect->to('/admin/backstage/goodies/categories');
            }

            $items = $this->goodiesService->getCategoryItems($category, ['active_only' => false]);

            return $this->response->withView(
                'backstage/goodies/categories/show',
                [
                    'user' => auth()->user(),
                    'category' => $category,
                    'items' => $items,
                    'can_edit' => BackstagePermissionHelper::canAdminGoodies(),
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading category', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('backstage.goodies.categories.error: ' . $e->getMessage(), NotificationType::ERROR); // phpcs:ignore
            return $this->redirect->to('/admin/backstage/goodies/categories');
        }
    }

    /**
     * Show form for editing the specified category.
     */
    public function editCategory(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $uuid = $request->getAttribute('uuid');

        try {
            $category = $this->goodiesService->getCategoryByUuid($uuid);

            if (!$category) {
                $this->addNotification('backstage.goodies.categories.not_found: Category not found', NotificationType::ERROR); // phpcs:ignore
                return $this->redirect->to('/admin/backstage/goodies/categories');
            }

            return $this->response->withView(
                'backstage/goodies/categories/edit',
                [
                    'user' => auth()->user(),
                    'category' => $category,
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading category for edit', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('backstage.goodies.categories.error: ' . $e->getMessage(), NotificationType::ERROR); // phpcs:ignore
            return $this->redirect->to('/admin/backstage/goodies/categories');
        }
    }

    /**
     * Update the specified category.
     */
    public function updateCategory(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $uuid = $request->getAttribute('uuid');
        $data = [
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'is_active' => $request->get('is_active'),
            'display_order' => $request->get('display_order'),
        ];

        try {
            $category = $this->goodiesService->getCategoryByUuid($uuid);

            if (!$category) {
                $this->addNotification('backstage.goodies.categories.not_found: Category not found', NotificationType::ERROR); // phpcs:ignore
                return $this->redirect->to('/admin/backstage/goodies/categories');
            }

            // Properly cast the data types
            $data['is_active'] = (bool) $request->get('is_active');
            $data['display_order'] = (int) $request->get('display_order', 0);

            $updatedCategory = $this->goodiesService->updateCategory($category, $data);

            $this->log->info('Category updated', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'uuid' => $uuid,
                'category_name' => $updatedCategory->name,
                'data' => $data,
            ]);

            $this->addNotification('backstage.goodies.categories.updated: Category updated successfully', NotificationType::MESSAGE); // phpcs:ignore
            return $this->redirect->to('/admin/backstage/goodies/categories/' . $uuid);
        } catch (\Exception $e) {
            $this->log->error('Error updating category', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('backstage.goodies.categories.update_error: ' . $e->getMessage(), NotificationType::ERROR); // phpcs:ignore
            return $this->redirect->back();
        }
    }

    /**
     * Remove the specified category.
     */
    public function destroyCategory(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $uuid = $request->getAttribute('uuid');

        try {
            $category = $this->goodiesService->getCategoryByUuid($uuid);

            if (!$category) {
                $this->addNotification('backstage.goodies.categories.not_found: Category not found', NotificationType::ERROR); // phpcs:ignore
                return $this->redirect->to('/admin/backstage/goodies/categories');
            }

            $deleted = $this->goodiesService->deleteCategory($category);

            if ($deleted) {
                $this->log->info('Category deleted successfully', [
                    'user' => auth()->user()->name,
                    'user_id' => auth()->user()->id,
                    'category_uuid' => $uuid,
                    'category_name' => $category->name,
                ]);

                $this->addNotification('backstage.goodies.categories.deleted: Category deleted successfully', NotificationType::MESSAGE); // phpcs:ignore
            } else {
                $this->addNotification('backstage.goodies.categories.delete_failed: Failed to delete category', NotificationType::ERROR); // phpcs:ignore
            }

            return $this->redirect->to('/admin/backstage/goodies/categories');
        } catch (\Exception $e) {
            $this->log->error('Error deleting category', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('backstage.goodies.categories.delete_error: ' . $e->getMessage(), NotificationType::ERROR); // phpcs:ignore
            return $this->redirect->back();
        }
    }

    // ========================================
    // ITEMS MANAGEMENT
    // ========================================

    /**
     * Display all goodies items.
     */
    public function itemsIndex(Request $request): Response
    {
        $this->checkGoodiesPermission('view');

        try {
            $itemsData = $this->goodiesService->getGoodiesItemsPaginated(1, 50, ['active_only' => false]); // phpcs:ignore
            $items = $itemsData['data'];
            $categories = $this->goodiesService->getAllCategories(['active_only' => false]);

            return $this->response->withView(
                'backstage/goodies/items/index',
                [
                    'user' => auth()->user(),
                    'items' => $items,
                    'categories' => $categories,
                    'can_edit' => BackstagePermissionHelper::canAdminGoodies(),
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading goodies items', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
            ]);

            $this->addNotification('backstage.goodies.items.error', NotificationType::ERROR);
            return $this->redirect->to('/admin/backstage');
        }
    }

    /**
     * Show form for creating a new item.
     */
    public function createItem(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        try {
            $categories = $this->goodiesService->getAllCategories(['active_only' => false]);
            $certifications = $this->certificationService->getAllCertifications(['active' => true]);

            return $this->response->withView(
                'backstage/goodies/items/create',
                [
                    'user' => auth()->user(),
                    'categories' => $categories,
                    'certifications' => $certifications,
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading item create form', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
            ]);

            $this->addNotification('backstage.goodies.items.form_error', NotificationType::ERROR);
            return $this->redirect->to('/admin/backstage/goodies/items');
        }
    }

    /**
     * Store a newly created item.
     */
    public function storeItem(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $data = [
            'category_uuid' => $request->get('category_uuid'),
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'required_hours' => $request->get('required_hours'),
            'max_per_person' => $request->get('max_per_person'),
            'is_active' => $request->get('is_active'),
            'display_order' => $request->get('display_order'),
            'certifications' => $request->get('certifications'),
        ];

        try {
            // Handle category UUID - convert to ID if needed
            $categoryUuid = $request->get('category_uuid');
            if ($categoryUuid && $categoryUuid !== '') {
                $category = $this->goodiesService->getCategoryByUuid($categoryUuid);
                if (!$category) {
                    $this->log->warning('Invalid category UUID provided', [
                        'category_uuid' => $categoryUuid,
                        'user' => auth()->user()->name,
                    ]);
                }
                $data['category_id'] = $category ? $category->id : null;
            } else {
                $data['category_id'] = null;
            }
            $data['required_hours'] = (int) $request->get('required_hours', 0);
            $data['max_per_person'] = $request->get('max_per_person') ?: null;
            $data['is_active'] = (bool) $request->get('is_active');
            $data['display_order'] = (int) $request->get('display_order', 0);
            $data['certifications'] = $request->get('certifications', []);

            $item = $this->goodiesService->createItem($data);

            $this->log->info('Item created', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'item_id' => $item->id,
                'item_uuid' => $item->uuid,
                'item_name' => $item->name,
                'category_id' => $item->category_id,
            ]);

            $this->addNotification('backstage.goodies.items.created: Item created successfully', NotificationType::MESSAGE); // phpcs:ignore
            return $this->redirect->to('/admin/backstage/goodies/items');
        } catch (\Exception $e) {
            $this->log->error('Error creating item', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            $this->addNotification('backstage.goodies.items.create_error', NotificationType::ERROR);
            return $this->redirect->back();
        }
    }

    /**
     * Display the specified item.
     */
    public function showItem(Request $request): Response
    {
        $this->checkGoodiesPermission('view');

        $uuid = $request->getAttribute('uuid');

        try {
            $item = $this->goodiesService->getItemByUuid($uuid);

            if (!$item) {
                $this->addNotification('backstage.goodies.items.not_found: Item not found', NotificationType::ERROR); // phpcs:ignore
                return $this->redirect->to('/admin/backstage/goodies/items');
            }

            // Load item with certifications and category
            $item->load(['certifications', 'category']);

            // TODO: Get distributions when GoodiesDistributionService is ready
            $distributions = []; // Item distributions

            return $this->response->withView(
                'backstage/goodies/items/show',
                [
                    'user' => auth()->user(),
                    'item' => $item,
                    'distributions' => $distributions,
                    'can_edit' => BackstagePermissionHelper::canAdminGoodies(),
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading item', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('backstage.goodies.items.error: ' . $e->getMessage(), NotificationType::ERROR); // phpcs:ignore
            return $this->redirect->to('/admin/backstage/goodies/items');
        }
    }

    /**
     * Show form for editing the specified item.
     */
    public function editItem(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $uuid = $request->getAttribute('uuid');

        try {
            $item = $this->goodiesService->getItemByUuid($uuid);

            if (!$item) {
                $this->addNotification('backstage.goodies.items.not_found: Item not found', NotificationType::ERROR); // phpcs:ignore
                return $this->redirect->to('/admin/backstage/goodies/items');
            }

            $categories = $this->goodiesService->getAllCategories();
            $certifications = $this->certificationService->getAllCertifications(['active' => true]);

            // Load item with certifications
            $item->load('certifications');

            // Debug logging for category selection
            $this->log->info('Edit item category data', [
                'item_uuid' => $item->uuid,
                'item_category_id' => $item->category_id,
                'item_category_id_type' => gettype($item->category_id),
                'categories_count' => count($categories),
                'first_category_id' => $categories[0]['id'] ?? null,
                'first_category_id_type' => isset($categories[0]['id']) ? gettype($categories[0]['id']) : null,
                'certifications_count' => $item->certifications->count(),
            ]);

            return $this->response->withView(
                'backstage/goodies/items/edit',
                [
                    'user' => auth()->user(),
                    'item' => $item,
                    'categories' => $categories,
                    'certifications' => $certifications,
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading item for edit', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('backstage.goodies.items.error: ' . $e->getMessage(), NotificationType::ERROR); // phpcs:ignore
            return $this->redirect->to('/admin/backstage/goodies/items');
        }
    }

    /**
     * Update the specified item.
     */
    public function updateItem(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $uuid = $request->getAttribute('uuid');
        $data = [
            'category_uuid' => $request->get('category_uuid'),
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'required_hours' => $request->get('required_hours'),
            'max_per_person' => $request->get('max_per_person'),
            'is_active' => $request->get('is_active'),
            'display_order' => $request->get('display_order'),
            'certifications' => $request->get('certifications'),
        ];

        try {
            $item = $this->goodiesService->getItemByUuid($uuid);

            if (!$item) {
                $this->addNotification('backstage.goodies.items.not_found: Item not found', NotificationType::ERROR); // phpcs:ignore
                return $this->redirect->to('/admin/backstage/goodies/items');
            }

            // Handle category UUID - convert to ID if needed
            $categoryUuid = $request->get('category_uuid');
            if ($categoryUuid && $categoryUuid !== '') {
                $category = $this->goodiesService->getCategoryByUuid($categoryUuid);
                if (!$category) {
                    $this->log->warning('Invalid category UUID provided', [
                        'category_uuid' => $categoryUuid,
                        'user' => auth()->user()->name,
                    ]);
                }
                $data['category_id'] = $category ? $category->id : null;
            } else {
                $data['category_id'] = null;
            }

            // Debug logging for form submission
            $this->log->info('Update item form data received', [
                'item_uuid' => $uuid,
                'raw_category_uuid' => $categoryUuid,
                'raw_category_uuid_type' => gettype($categoryUuid),
                'current_item_category_id' => $item->category_id,
                'processed_category_id' => $data['category_id'],
                'all_form_data' => $request->getParsedBody(),
            ]);
            $data['required_hours'] = (int) $request->get('required_hours', 0);
            $data['max_per_person'] = $request->get('max_per_person') ?: null;
            $data['is_active'] = (bool) $request->get('is_active');
            $data['display_order'] = (int) $request->get('display_order', 0);
            $data['certifications'] = $request->get('certifications', []);

            $updatedItem = $this->goodiesService->updateItem($item, $data);

            $this->log->info('Item updated', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'uuid' => $uuid,
                'item_name' => $updatedItem->name,
                'data' => $data,
            ]);

            $this->addNotification('backstage.goodies.items.updated: Item updated successfully', NotificationType::MESSAGE); // phpcs:ignore
            return $this->redirect->to('/admin/backstage/goodies/items/' . $uuid);
        } catch (\Exception $e) {
            $this->log->error('Error updating item', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('backstage.goodies.items.update_error: ' . $e->getMessage(), NotificationType::ERROR); // phpcs:ignore
            return $this->redirect->back();
        }
    }

    /**
     * Remove the specified item.
     */
    public function destroyItem(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $uuid = $request->getAttribute('uuid');

        try {
            $item = $this->goodiesService->getItemByUuid($uuid);

            if (!$item) {
                $this->addNotification('backstage.goodies.items.not_found: Item not found', NotificationType::ERROR); // phpcs:ignore
                return $this->redirect->to('/admin/backstage/goodies/items');
            }

            $deleted = $this->goodiesService->deleteItem($item);

            if ($deleted) {
                $this->log->info('Item deleted successfully', [
                    'user' => auth()->user()->name,
                    'user_id' => auth()->user()->id,
                    'item_uuid' => $uuid,
                    'item_name' => $item->name,
                ]);

                $this->addNotification('backstage.goodies.items.deleted: Item deleted successfully', NotificationType::MESSAGE); // phpcs:ignore
            } else {
                $this->addNotification('backstage.goodies.items.delete_failed: Failed to delete item', NotificationType::ERROR); // phpcs:ignore
            }

            return $this->redirect->to('/admin/backstage/goodies/items');
        } catch (\Exception $e) {
            $this->log->error('Error deleting item', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('backstage.goodies.items.delete_error: ' . $e->getMessage(), NotificationType::ERROR); // phpcs:ignore
            return $this->redirect->back();
        }
    }

    // ========================================
    // DISTRIBUTION MANAGEMENT
    // ========================================

    /**
     * Display distribution history and management.
     */
    public function distributionsIndex(Request $request): Response
    {
        $this->checkGoodiesPermission('view');

        try {
            // TODO: Use GoodiesDistributionService when available
            $distributions = []; // Recent distributions

            return $this->response->withView(
                'backstage/goodies/distributions/index',
                [
                    'user' => auth()->user(),
                    'distributions' => $distributions,
                    'can_distribute' => BackstagePermissionHelper::canDistributeGoodies(),
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading distributions', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
            ]);

            $this->addNotification('backstage.goodies.distributions.error', NotificationType::ERROR);
            return $this->redirect->to('/admin/backstage');
        }
    }

    /**
     * Process goodie distribution.
     */
    public function distribute(Request $request): Response
    {
        $this->checkGoodiesPermission('view');

        if (!BackstagePermissionHelper::canDistributeGoodies()) {
            $this->addNotification('backstage.goodies.distribute.insufficient_permissions', NotificationType::ERROR);
            return $this->redirect->back();
        }

        $data = [
            'user_id' => $request->get('user_id'),
            'item_id' => $request->get('item_id'),
            'notes' => $request->get('notes'),
        ];

        try {
            // TODO: Implement actual distribution when GoodiesDistributionService is ready

            $this->log->info('Goodie distributed (placeholder)', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'distribution_data' => $data,
            ]);

            $this->addNotification('backstage.goodies.distribute.success', NotificationType::MESSAGE);
            return $this->redirect->to('/admin/backstage/goodies/distributions');
        } catch (\Exception $e) {
            $this->log->error('Error distributing goodie', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            $this->addNotification('backstage.goodies.distribute.error', NotificationType::ERROR);
            return $this->redirect->back();
        }
    }

    /**
     * Display distribution reports.
     */
    public function reports(Request $request): Response
    {
        $this->checkGoodiesPermission('view');

        try {
            // TODO: Use GoodiesDistributionService when available
            $reportData = []; // Distribution statistics and reports

            return $this->response->withView(
                'backstage/goodies/reports',
                [
                    'user' => auth()->user(),
                    'report_data' => $reportData,
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading distribution reports', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
            ]);

            $this->addNotification('backstage.goodies.reports.error', NotificationType::ERROR);
            return $this->redirect->to('/admin/backstage');
        }
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Check goodies permissions with detailed logging.
     */
    protected function checkGoodiesPermission(string $level): void
    {
        $user = auth()->user();

        if (!$user) {
            $this->log->warning('Unauthenticated access attempt to backstage goodies', [
                'ip_address' => request()->getClientIp(),
                'user_agent' => request()->getHeaderLine('User-Agent'),
                'requested_level' => $level,
            ]);
            throw new HttpForbidden('Authentication required');
        }

        $hasAccess = match ($level) {
            'view' => BackstagePermissionHelper::hasViewAccess($user) ||
                     BackstagePermissionHelper::canViewGoodies($user),
            'admin' => BackstagePermissionHelper::canAdminGoodies($user) ||
                      BackstagePermissionHelper::hasAdminAccess($user),
            default => false,
        };

        if (!$hasAccess) {
            $this->log->warning('Insufficient permissions for backstage goodies access', [
                'user' => $user->name,
                'user_id' => $user->id,
                'requested_level' => $level,
                'user_permissions' => $user->privileges->pluck('name')->toArray(),
                'ip_address' => request()->getClientIp(),
            ]);
            throw new HttpForbidden('Insufficient permissions');
        }

        // Log successful access for audit trail
        $this->log->info('Backstage goodies access granted', [
            'user' => $user->name,
            'user_id' => $user->id,
            'level' => $level,
            'timestamp' => Carbon::now()->toISOString(),
        ]);
    }
}

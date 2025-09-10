<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\Admin;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Controllers\HasUserNotifications;
use Engelsystem\Controllers\NotificationType;
use Engelsystem\Helpers\BackstagePermissionHelper;
use Engelsystem\Http\Exceptions\HttpForbidden;
use Engelsystem\Http\Exceptions\ValidationException;
use Engelsystem\Http\Redirector;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Http\Validation\Validator;
use Engelsystem\Models\GoodiesV2Category;
use Engelsystem\Models\GoodiesV2Item;
use Engelsystem\Services\GoodiesService;
use Psr\Log\LoggerInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GoodiesV2Controller extends BaseController
{
    use HasUserNotifications;

    /** @var array<string> */
    protected array $permissions = [
        'backstage.goodies.view',
        'backstage.goodies.admin',
    ];

    public function __construct(
        protected LoggerInterface $log,
        protected GoodiesService $goodiesService,
        protected Response $response,
        protected Redirector $redirect
    ) {
    }

    /**
     * Display a listing of goodies categories and items.
     */
    public function index(Request $request): Response
    {
        $this->checkGoodiesPermission('view');

        try {
            // Get filter parameters
            $search = $request->get('search', '');
            $categoryId = $request->get('category_id', '');
            $activeOnly = $request->get('active_only', 'true') === 'true';

            $filters = [
                'search' => $search,
                'active_only' => $activeOnly,
            ];

            if (!empty($categoryId) && is_numeric($categoryId)) {
                $filters['category_id'] = (int) $categoryId;
            }

            // Get pagination parameters
            $perPage = max(10, min(100, (int) $request->get('per_page', 25)));
            $page = max(1, (int) $request->get('page', 1));

            // Get categories for filter dropdown
            $categories = $this->goodiesService->getAllCategories(['active' => true]);

            // Get paginated items
            $itemsResult = $this->goodiesService->getGoodiesItemsPaginated($page, $perPage, $filters);

            // Get summary statistics
            $statistics = [
                'total_categories' => GoodiesV2Category::count(),
                'active_categories' => GoodiesV2Category::where('is_active', true)->count(),
                'total_items' => GoodiesV2Item::count(),
                'active_items' => GoodiesV2Item::where('is_active', true)->count(),
                'total_stock' => GoodiesV2Item::where('is_active', true)->sum('stock_quantity'),
            ];

            $this->log->info('Admin accessed goodies management interface', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
            ]);

            return $this->response->withView(
                'admin/goodies-v2/index',
                [
                    'items' => $itemsResult['data'] ?? [],
                    'categories' => $categories,
                    'statistics' => $statistics,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $itemsResult['total'] ?? 0,
                        'total_pages' => $itemsResult['total_pages'] ?? 1,
                    ],
                    'filters' => $filters,
                    'user' => auth()->user(),
                    'can_admin' => BackstagePermissionHelper::canManageGoodies(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading goodies management interface', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('goodies.admin.load.error', NotificationType::ERROR);

            return $this->response->withView(
                'admin/goodies-v2/index',
                [
                    'error' => true,
                    'items' => [],
                    'categories' => [],
                    'statistics' => [],
                    'user' => auth()->user(),
                ]
            );
        }
    }

    /**
     * Show the form for creating a new goodies item or category.
     */
    public function create(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $type = $request->get('type', 'item'); // 'item' or 'category'

        if ($type === 'category') {
            return $this->showCreateCategoryForm();
        }

        return $this->showCreateItemForm();
    }

    /**
     * Store a newly created goodies item or category.
     */
    public function store(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $type = $request->get('type', 'item');

        if ($type === 'category') {
            return $this->storeCategory($request);
        }

        return $this->storeItem($request);
    }

    /**
     * Display the specified goodies item or category.
     */
    public function show(Request $request): Response
    {
        $this->checkGoodiesPermission('view');

        $type = $request->get('type', 'item');
        $id = (int) $request->getAttribute('id');

        try {
            if ($type === 'category') {
                return $this->showCategory($id);
            }

            return $this->showItem($id);
        } catch (ModelNotFoundException) {
            $this->addNotification('goodies.admin.not_found', NotificationType::ERROR);
            return $this->redirect->to('/admin/goodies-v2');
        }
    }

    /**
     * Show the form for editing the specified goodies item or category.
     */
    public function edit(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $type = $request->get('type', 'item');
        $id = (int) $request->getAttribute('id');

        try {
            if ($type === 'category') {
                return $this->showEditCategoryForm($id);
            }

            return $this->showEditItemForm($id);
        } catch (ModelNotFoundException) {
            $this->addNotification('goodies.admin.not_found', NotificationType::ERROR);
            return $this->redirect->to('/admin/goodies-v2');
        }
    }

    /**
     * Update the specified goodies item or category.
     */
    public function update(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $type = $request->get('type', 'item');
        $id = (int) $request->getAttribute('id');

        if ($type === 'category') {
            return $this->updateCategory($request, $id);
        }

        return $this->updateItem($request, $id);
    }

    /**
     * Remove the specified goodies item or category.
     */
    public function destroy(Request $request): Response
    {
        $this->checkGoodiesPermission('admin');

        $type = $request->get('type', 'item');
        $id = (int) $request->getAttribute('id');

        $this->validate($request, [
            'delete' => 'checked',
        ]);

        try {
            if ($type === 'category') {
                return $this->destroyCategory($id);
            }

            return $this->destroyItem($id);
        } catch (ModelNotFoundException) {
            $this->addNotification('goodies.admin.not_found', NotificationType::ERROR);
            return $this->redirect->to('/admin/goodies-v2');
        }
    }

    /**
     * Store a new category.
     */
    protected function storeCategory(Request $request): Response
    {
        $data = $this->validateCategoryData($request);

        try {
            $category = $this->goodiesService->createCategory([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->log->info('Created goodies category', [
                'admin' => auth()->user()->name,
                'admin_id' => auth()->user()->id,
                'category_id' => $category->id,
                'category_name' => $category->name,
            ]);

            $this->addNotification('goodies.admin.category.create.success');
            return $this->redirect->to('/admin/goodies-v2');
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException(
                (new Validator())->addErrors(['general' => [$e->getMessage()]])
            );
        }
    }

    /**
     * Store a new item.
     */
    protected function storeItem(Request $request): Response
    {
        $data = $this->validateItemData($request);

        try {
            $item = $this->goodiesService->createItem([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'],
                'stock_quantity' => $data['stock_quantity'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->log->info('Created goodies item', [
                'admin' => auth()->user()->name,
                'admin_id' => auth()->user()->id,
                'item_id' => $item->id,
                'item_name' => $item->name,
                'category_id' => $item->category_id,
                'stock_quantity' => $item->stock_quantity,
            ]);

            $this->addNotification('goodies.admin.item.create.success');
            return $this->redirect->to('/admin/goodies-v2');
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException(
                (new Validator())->addErrors(['general' => [$e->getMessage()]])
            );
        }
    }

    /**
     * Update an existing category.
     */
    protected function updateCategory(Request $request, int $id): Response
    {
        /** @var GoodiesV2Category $category */
        $category = GoodiesV2Category::findOrFail($id);
        $data = $this->validateCategoryData($request, $category);

        try {
            $category = $this->goodiesService->updateCategory($category, [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? $category->is_active,
            ]);

            $this->log->info('Updated goodies category', [
                'admin' => auth()->user()->name,
                'admin_id' => auth()->user()->id,
                'category_id' => $category->id,
                'category_name' => $category->name,
                'changes' => $data,
            ]);

            $this->addNotification('goodies.admin.category.update.success');
            return $this->redirect->to('/admin/goodies-v2');
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException(
                (new Validator())->addErrors(['general' => [$e->getMessage()]])
            );
        }
    }

    /**
     * Update an existing item.
     */
    protected function updateItem(Request $request, int $id): Response
    {
        /** @var GoodiesV2Item $item */
        $item = GoodiesV2Item::findOrFail($id);
        $data = $this->validateItemData($request, $item);

        try {
            $item = $this->goodiesService->updateItem($item, [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'],
                'stock_quantity' => $data['stock_quantity'],
                'is_active' => $data['is_active'] ?? $item->is_active,
            ]);

            $this->log->info('Updated goodies item', [
                'admin' => auth()->user()->name,
                'admin_id' => auth()->user()->id,
                'item_id' => $item->id,
                'item_name' => $item->name,
                'changes' => $data,
            ]);

            $this->addNotification('goodies.admin.item.update.success');
            return $this->redirect->to('/admin/goodies-v2');
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException(
                (new Validator())->addErrors(['general' => [$e->getMessage()]])
            );
        }
    }

    /**
     * Validate category form data.
     */
    protected function validateCategoryData(Request $request, ?GoodiesV2Category $category = null): array
    {
        $rules = [
            'name' => 'required|min:3|max:100',
            'description' => 'optional|max:500',
            'is_active' => 'optional|checked',
        ];

        $data = $this->validate($request, $rules);

        // Check name uniqueness
        $query = GoodiesV2Category::where('name', $data['name']);
        if ($category) {
            $query->where('id', '!=', $category->id);
        }

        if ($query->exists()) {
            throw new ValidationException(
                (new Validator())->addErrors(['name' => ['A category with this name already exists']])
            );
        }

        // Clean data
        $data['name'] = globalCleanText($data['name'], true);
        $data['description'] = !empty($data['description']) ? globalCleanText($data['description']) : null;
        $data['is_active'] = isset($data['is_active']);

        return $data;
    }

    /**
     * Validate item form data.
     */
    protected function validateItemData(Request $request, ?GoodiesV2Item $item = null): array
    {
        $rules = [
            'name' => 'required|min:3|max:100',
            'description' => 'optional|max:1000',
            'category_id' => 'required|integer|min:1',
            'stock_quantity' => 'required|integer|min:0',
            'is_active' => 'optional|checked',
        ];

        $data = $this->validate($request, $rules);

        // Validate category exists and is active
        $category = GoodiesV2Category::find($data['category_id']);
        if (!$category || !$category->is_active) {
            throw new ValidationException(
                (new Validator())->addErrors(['category_id' => ['Selected category is not valid or inactive']])
            );
        }

        // Clean data
        $data['name'] = globalCleanText($data['name'], true);
        $data['description'] = !empty($data['description']) ? globalCleanText($data['description']) : null;
        $data['is_active'] = isset($data['is_active']);

        return $data;
    }

    /**
     * Show create category form.
     */
    protected function showCreateCategoryForm(): Response
    {
        return $this->response->withView(
            'admin/goodies-v2/create-category',
            [
                'user' => auth()->user(),
            ]
        );
    }

    /**
     * Show create item form.
     */
    protected function showCreateItemForm(): Response
    {
        $categories = $this->goodiesService->getAllCategories(['active' => true]);

        return $this->response->withView(
            'admin/goodies-v2/create-item',
            [
                'categories' => $categories,
                'user' => auth()->user(),
            ]
        );
    }

    /**
     * Show category details.
     */
    protected function showCategory(int $id): Response
    {
        $category = GoodiesV2Category::with('items')->findOrFail($id);

        return $this->response->withView(
            'admin/goodies-v2/show-category',
            [
                'category' => $category,
                'user' => auth()->user(),
                'can_admin' => BackstagePermissionHelper::canManageGoodies(),
            ]
        );
    }

    /**
     * Show item details.
     */
    protected function showItem(int $id): Response
    {
        $item = GoodiesV2Item::with('category')->findOrFail($id);

        return $this->response->withView(
            'admin/goodies-v2/show-item',
            [
                'item' => $item,
                'user' => auth()->user(),
                'can_admin' => BackstagePermissionHelper::canManageGoodies(),
            ]
        );
    }

    /**
     * Show edit category form.
     */
    protected function showEditCategoryForm(int $id): Response
    {
        $category = GoodiesV2Category::findOrFail($id);

        return $this->response->withView(
            'admin/goodies-v2/edit-category',
            [
                'category' => $category,
                'user' => auth()->user(),
            ]
        );
    }

    /**
     * Show edit item form.
     */
    protected function showEditItemForm(int $id): Response
    {
        $item = GoodiesV2Item::with('category')->findOrFail($id);
        $categories = $this->goodiesService->getAllCategories(['active' => true]);

        return $this->response->withView(
            'admin/goodies-v2/edit-item',
            [
                'item' => $item,
                'categories' => $categories,
                'user' => auth()->user(),
            ]
        );
    }

    /**
     * Delete a category.
     */
    protected function destroyCategory(int $id): Response
    {
        $category = GoodiesV2Category::findOrFail($id);

        try {
            $this->goodiesService->deleteCategory($category);

            $this->log->info('Deleted goodies category', [
                'admin' => auth()->user()->name,
                'admin_id' => auth()->user()->id,
                'category_id' => $id,
                'category_name' => $category->name,
            ]);

            $this->addNotification('goodies.admin.category.delete.success');
        } catch (\RuntimeException $e) {
            $this->addNotification($e->getMessage(), NotificationType::ERROR);
        }

        return $this->redirect->to('/admin/goodies-v2');
    }

    /**
     * Delete an item.
     */
    protected function destroyItem(int $id): Response
    {
        $item = GoodiesV2Item::findOrFail($id);

        try {
            $this->goodiesService->deleteItem($item);

            $this->log->info('Deleted goodies item', [
                'admin' => auth()->user()->name,
                'admin_id' => auth()->user()->id,
                'item_id' => $id,
                'item_name' => $item->name,
            ]);

            $this->addNotification('goodies.admin.item.delete.success');
        } catch (\RuntimeException $e) {
            $this->addNotification($e->getMessage(), NotificationType::ERROR);
        }

        return $this->redirect->to('/admin/goodies-v2');
    }

    /**
     * Check goodies permissions.
     */
    protected function checkGoodiesPermission(string $level): void
    {
        $user = auth()->user();

        if (!$user) {
            throw new HttpForbidden('Authentication required');
        }

        $hasPermission = match ($level) {
            'view' => BackstagePermissionHelper::canViewGoodies($user),
            'admin' => BackstagePermissionHelper::canManageGoodies($user),
            default => false,
        };

        if (!$hasPermission) {
            $this->log->warning('Insufficient permissions for goodies admin access', [
                'user' => $user->name,
                'user_id' => $user->id,
                'requested_level' => $level,
                'user_permissions' => $user->privileges->pluck('name')->toArray(),
            ]);
            throw new HttpForbidden('Insufficient permissions');
        }
    }
}

<?php
// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create':
                $data = [
                    'name' => trim($_POST['name']),
                    'slug' => trim($_POST['slug']),
                    'description' => trim($_POST['description']),
                    'thumbnail_url' => trim($_POST['thumbnail_url']) ?: null,
                    'sort_order' => (int)($_POST['sort_order'] ?? 0)
                ];

                if (empty($data['name']) || empty($data['slug'])) {
                    throw new Exception('Name and slug are required');
                }

                $categoryId = Category::create($data);
                if ($categoryId) {
                    $message = 'Category created successfully';
                    $messageType = 'success';
                } else {
                    throw new Exception('Failed to create category');
                }
                break;

            case 'update':
                $categoryId = (int)$_POST['category_id'];
                $data = [
                    'name' => trim($_POST['name']),
                    'slug' => trim($_POST['slug']),
                    'description' => trim($_POST['description']),
                    'thumbnail_url' => trim($_POST['thumbnail_url']) ?: null,
                    'sort_order' => (int)($_POST['sort_order'] ?? 0)
                ];

                if (Category::update($categoryId, $data)) {
                    $message = 'Category updated successfully';
                    $messageType = 'success';
                } else {
                    throw new Exception('Failed to update category');
                }
                break;

            case 'delete':
                $categoryId = (int)$_POST['category_id'];

                if (Category::delete($categoryId)) {
                    $message = 'Category deleted successfully';
                    $messageType = 'success';
                } else {
                    throw new Exception('Failed to delete category');
                }
                break;
        }

        // Refresh categories list
        $categories = Category::getActiveCategories();

    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get fresh categories list
$categories = Category::getActiveCategories();
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Categories Management</h1>
            <p class="text-gray-600">Organize your content into categories</p>
        </div>
        <button
            onclick="openModal('create')"
            class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors"
        >
            <i class="fas fa-plus mr-2"></i> Add Category
        </button>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="rounded-md p-4 <?php
        echo $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200';
    ?>">
        <div class="flex">
            <div class="flex-shrink-0">
                <?php if ($messageType === 'success'): ?>
                <i class="fas fa-check-circle text-green-400"></i>
                <?php else: ?>
                <i class="fas fa-exclamation-circle text-red-400"></i>
                <?php endif; ?>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium <?php
                    echo $messageType === 'success' ? 'text-green-800' : 'text-red-800';
                ?>">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Categories Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($categories)): ?>
        <div class="col-span-full bg-white rounded-lg shadow-sm p-12 text-center">
            <i class="fas fa-folder text-4xl text-gray-400 mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Categories</h3>
            <p class="text-gray-600 mb-4">Create your first category to organize content</p>
            <button
                onclick="openModal('create')"
                class="bg-orange-500 text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition-colors"
            >
                Create Category
            </button>
        </div>
        <?php else: ?>
        <?php foreach ($categories as $category): ?>
        <?php
            $videoCount = count(Video::getByCategory($category['id']));
        ?>
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="h-32 bg-gradient-to-r from-orange-400 to-orange-600 flex items-center justify-center">
                <?php if ($category['thumbnail_url']): ?>
                <img
                    src="<?php echo htmlspecialchars($category['thumbnail_url']); ?>"
                    alt="<?php echo htmlspecialchars($category['name']); ?>"
                    class="w-full h-full object-cover"
                >
                <?php else: ?>
                <div class="text-white text-4xl font-bold">
                    <?php echo strtoupper(substr($category['name'], 0, 1)); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </h3>
                    <span class="text-sm text-gray-500">
                        #<?php echo $category['sort_order']; ?>
                    </span>
                </div>

                <p class="text-gray-600 text-sm mb-4">
                    <?php echo htmlspecialchars($category['description']); ?>
                </p>

                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">
                        <?php echo $videoCount; ?> videos
                    </span>
                    <div class="flex space-x-2">
                        <button
                            onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($category)); ?>)"
                            class="text-blue-600 hover:text-blue-800 text-sm"
                        >
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this category? All associated videos will remain.')" style="display: inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="categoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 id="modalTitle" class="text-lg font-medium text-gray-900">Create Category</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="categoryForm" method="POST" class="space-y-4">
                <input type="hidden" id="actionInput" name="action" value="create">
                <input type="hidden" id="categoryIdInput" name="category_id" value="">

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-700">Slug</label>
                    <input
                        type="text"
                        id="slug"
                        name="slug"
                        required
                        pattern="[a-z0-9-]+"
                        title="Only lowercase letters, numbers, and hyphens allowed"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                    <p class="mt-1 text-sm text-gray-500">URL-friendly identifier</p>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    ></textarea>
                </div>

                <div>
                    <label for="thumbnail_url" class="block text-sm font-medium text-gray-700">Thumbnail URL</label>
                    <input
                        type="url"
                        id="thumbnail_url"
                        name="thumbnail_url"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <div>
                    <label for="sort_order" class="block text-sm font-medium text-gray-700">Sort Order</label>
                    <input
                        type="number"
                        id="sort_order"
                        name="sort_order"
                        min="0"
                        value="0"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button
                        type="button"
                        onclick="closeModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-orange-500 border border-transparent rounded-md hover:bg-orange-600"
                    >
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(action, category = null) {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    const title = document.getElementById('modalTitle');
    const actionInput = document.getElementById('actionInput');
    const categoryIdInput = document.getElementById('categoryIdInput');

    // Reset form
    form.reset();

    if (action === 'create') {
        title.textContent = 'Create Category';
        actionInput.value = 'create';
        categoryIdInput.value = '';
    } else if (action === 'edit' && category) {
        title.textContent = 'Edit Category';
        actionInput.value = 'update';
        categoryIdInput.value = category.id;

        // Populate form
        document.getElementById('name').value = category.name || '';
        document.getElementById('slug').value = category.slug || '';
        document.getElementById('description').value = category.description || '';
        document.getElementById('thumbnail_url').value = category.thumbnail_url || '';
        document.getElementById('sort_order').value = category.sort_order || 0;
    }

    modal.classList.remove('hidden');
}

function closeModal() {
    const modal = document.getElementById('categoryModal');
    modal.classList.add('hidden');
}

// Auto-generate slug from name
document.getElementById('name').addEventListener('input', function(e) {
    const name = e.target.value;
    const slug = name.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
    document.getElementById('slug').value = slug;
});
</script>

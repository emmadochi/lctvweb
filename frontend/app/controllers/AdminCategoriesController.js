/**
 * Admin Categories Controller
 * Handles category management for administrators
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('AdminCategoriesController', ['$scope', '$location', '$timeout', 'AdminService',
            function($scope, $location, $timeout, AdminService) {

            var vm = this;

            // Controller properties
            vm.categories = [];
            vm.isLoading = true;

            // Modal states
            vm.showAddModal = false;
            vm.showEditModal = false;
            vm.editingCategory = null;

            // New category form
            vm.newCategory = {
                name: '',
                slug: '',
                description: '',
                thumbnail_url: '',
                sort_order: 0
            };

            // Initialize controller
            init();

            function init() {
                // Check authentication
                if (!AdminService.isAuthenticated()) {
                    $location.path('/admin/login');
                    return;
                }

                // Load categories
                loadCategories();

                // Set page title
                $scope.$root.setPageTitle('Category Management');
            }

            /**
             * Load categories
             */
            function loadCategories() {
                vm.isLoading = true;

                AdminService.getCategories()
                    .then(function(categories) {
                        vm.categories = categories;
                        vm.isLoading = false;
                    })
                    .catch(function(error) {
                        console.error('Error loading categories:', error);
                        vm.categories = [];
                        vm.isLoading = false;
                        $scope.$root.showError('Failed to load categories');
                    });
            }

            /**
             * Generate slug from name
             */
            vm.generateSlug = function(name) {
                if (!name) return '';

                return name
                    .toLowerCase()
                    .replace(/[^\w\s-]/g, '') // Remove special characters
                    .replace(/[\s_-]+/g, '-') // Replace spaces and underscores with hyphens
                    .replace(/^-+|-+$/g, ''); // Remove leading/trailing hyphens
            };

            /**
             * Auto-generate slug when name changes
             */
            vm.onNameChange = function(category) {
                if (category.slug === '' || category.slug === vm.generateSlug(category.originalName || '')) {
                    category.slug = vm.generateSlug(category.name);
                }
                if (category === vm.newCategory) {
                    vm.newCategory.originalName = category.name;
                }
            };

            /**
             * Open add category modal
             */
            vm.openAddModal = function() {
                vm.newCategory = {
                    name: '',
                    slug: '',
                    description: '',
                    thumbnail_url: '',
                    sort_order: 0
                };
                vm.showAddModal = true;
                $timeout(function() {
                    $('#addCategoryModal').modal('show');
                });
            };

            /**
             * Close add category modal
             */
            vm.closeAddModal = function() {
                vm.showAddModal = false;
                $('#addCategoryModal').modal('hide');
            };

            /**
             * Add new category
             */
            vm.addCategory = function() {
                if (!vm.newCategory.name || !vm.newCategory.slug) {
                    $scope.$root.showError('Name and slug are required');
                    return;
                }

                // Ensure slug is valid
                vm.newCategory.slug = vm.generateSlug(vm.newCategory.slug);

                AdminService.addCategory(vm.newCategory)
                    .then(function(response) {
                        vm.closeAddModal();
                        loadCategories(); // Reload the list
                        $scope.$root.showToast('Category added successfully', 'success');
                    })
                    .catch(function(error) {
                        console.error('Error adding category:', error);
                        $scope.$root.showError(error.data?.message || 'Failed to add category');
                    });
            };

            /**
             * Open edit category modal
             */
            vm.openEditModal = function(category) {
                vm.editingCategory = angular.copy(category);
                vm.editingCategory.originalName = category.name;
                vm.showEditModal = true;
                $timeout(function() {
                    $('#editCategoryModal').modal('show');
                });
            };

            /**
             * Close edit category modal
             */
            vm.closeEditModal = function() {
                vm.showEditModal = false;
                vm.editingCategory = null;
                $('#editCategoryModal').modal('hide');
            };

            /**
             * Update category
             */
            vm.updateCategory = function() {
                if (!vm.editingCategory) return;

                // Ensure slug is valid
                vm.editingCategory.slug = vm.generateSlug(vm.editingCategory.slug);

                AdminService.updateCategory(vm.editingCategory.id, vm.editingCategory)
                    .then(function(response) {
                        vm.closeEditModal();
                        loadCategories(); // Reload the list
                        $scope.$root.showToast('Category updated successfully', 'success');
                    })
                    .catch(function(error) {
                        console.error('Error updating category:', error);
                        $scope.$root.showError(error.data?.message || 'Failed to update category');
                    });
            };

            /**
             * Delete category
             */
            vm.deleteCategory = function(category) {
                if (!confirm('Are you sure you want to delete "' + category.name + '"? This will affect all videos in this category.')) {
                    return;
                }

                AdminService.deleteCategory(category.id)
                    .then(function(response) {
                        loadCategories(); // Reload the list
                        $scope.$root.showToast('Category deleted successfully', 'success');
                    })
                    .catch(function(error) {
                        console.error('Error deleting category:', error);
                        $scope.$root.showError(error.data?.message || 'Failed to delete category');
                    });
            };

            /**
             * Toggle category status (active/inactive)
             */
            vm.toggleStatus = function(category) {
                var newStatus = category.is_active ? 0 : 1;
                var action = newStatus ? 'activate' : 'deactivate';

                AdminService.updateCategory(category.id, { is_active: newStatus })
                    .then(function(response) {
                        category.is_active = newStatus;
                        $scope.$root.showToast('Category ' + action + 'd successfully', 'success');
                    })
                    .catch(function(error) {
                        console.error('Error updating category status:', error);
                        $scope.$root.showError('Failed to ' + action + ' category');
                    });
            };

            /**
             * Move category up in sort order
             */
            vm.moveUp = function(category) {
                var currentIndex = vm.categories.indexOf(category);
                if (currentIndex > 0) {
                    var prevCategory = vm.categories[currentIndex - 1];

                    // Swap sort orders
                    var tempOrder = category.sort_order;
                    category.sort_order = prevCategory.sort_order;
                    prevCategory.sort_order = tempOrder;

                    // Update both categories
                    Promise.all([
                        AdminService.updateCategory(category.id, { sort_order: category.sort_order }),
                        AdminService.updateCategory(prevCategory.id, { sort_order: prevCategory.sort_order })
                    ]).then(function() {
                        loadCategories(); // Reload to show new order
                        $scope.$root.showToast('Category moved up', 'success');
                    }).catch(function(error) {
                        console.error('Error moving category:', error);
                        $scope.$root.showError('Failed to move category');
                        loadCategories(); // Reload to revert changes
                    });
                }
            };

            /**
             * Move category down in sort order
             */
            vm.moveDown = function(category) {
                var currentIndex = vm.categories.indexOf(category);
                if (currentIndex < vm.categories.length - 1) {
                    var nextCategory = vm.categories[currentIndex + 1];

                    // Swap sort orders
                    var tempOrder = category.sort_order;
                    category.sort_order = nextCategory.sort_order;
                    nextCategory.sort_order = tempOrder;

                    // Update both categories
                    Promise.all([
                        AdminService.updateCategory(category.id, { sort_order: category.sort_order }),
                        AdminService.updateCategory(nextCategory.id, { sort_order: nextCategory.sort_order })
                    ]).then(function() {
                        loadCategories(); // Reload to show new order
                        $scope.$root.showToast('Category moved down', 'success');
                    }).catch(function(error) {
                        console.error('Error moving category:', error);
                        $scope.$root.showError('Failed to move category');
                        loadCategories(); // Reload to revert changes
                    });
                }
            };

            /**
             * Get video count for category
             */
            vm.getVideoCount = function(category) {
                // This would ideally come from the API
                // For now, return a placeholder
                return category.video_count || 0;
            };

            /**
             * Get category icon
             */
            vm.getCategoryIcon = function(category) {
                return category.icon || 'fa-tag';
            };

            // Sort categories by sort_order
            vm.sortedCategories = function() {
                return vm.categories.sort(function(a, b) {
                    return (a.sort_order || 0) - (b.sort_order || 0);
                });
            };

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();
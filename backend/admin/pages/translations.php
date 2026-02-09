<?php
/**
 * Translations Management Admin Page
 * Interface for managing multi-language content translations
 */

// Prevent direct access - this file should be included from admin/index.php
if (!defined('ADMIN_ACCESS') && !isset($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit();
}

$message = '';
$messageType = '';

require_once __DIR__ . '/../../utils/Auth.php';

// Check admin authentication
if (!Auth::isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$user = Auth::getUserFromToken();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'set_translation':
                if (!empty($_POST['language_code']) && !empty($_POST['key'])) {
                    $languagePath = realpath(__DIR__ . '/../../models/Language.php');
                    if (!$languagePath) {
                        die('Language model not found');
                    }
                    require_once $languagePath;
                    $success = Language::setTranslation(
                        $_POST['language_code'],
                        $_POST['key'],
                        $_POST['text'],
                        $_POST['category'] ?? 'general'
                    );

                    if ($success) {
                        $message = 'Translation updated successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update translation';
                        $messageType = 'error';
                    }
                }
                break;

            case 'import_translations':
                if (!empty($_POST['language_code']) && !empty($_POST['translations_json'])) {
                    $languagePath = realpath(__DIR__ . '/../../models/Language.php');
                    if (!$languagePath) {
                        die('Language model not found');
                    }
                    require_once $languagePath;
                    $translations = json_decode($_POST['translations_json'], true);

                    if ($translations) {
                        $result = Language::importTranslations($_POST['language_code'], $translations);
                        $message = "Imported {$result['success']} translations, {$result['errors']} errors";
                        $messageType = $result['errors'] > 0 ? 'warning' : 'success';
                    } else {
                        $message = 'Invalid JSON format';
                        $messageType = 'error';
                    }
                }
                break;

            case 'set_content_translation':
                if (!empty($_POST['content_type']) && !empty($_POST['content_id']) && !empty($_POST['language_code'])) {
                    $controllerPath = realpath(__DIR__ . '/../../controllers/LanguageController.php');
                    if (!$controllerPath) {
                        die('Language controller not found');
                    }
                    require_once $controllerPath;
                    // This would need to be adapted for form submission
                    $message = 'Content translation feature coming soon';
                    $messageType = 'info';
                }
                break;
        }
    }
}

// Load data for the page
$languageModelPath = realpath(__DIR__ . '/../../models/Language.php');
if (!$languageModelPath) {
    die('Language model not found');
}
require_once $languageModelPath;
$languages = Language::getAll();
$languageStats = Language::getStats();
$categories = Language::getCategories();

// Get current language for editing (default to English)
$currentLang = $_GET['lang'] ?? 'en';
$translations = Language::getTranslations($currentLang);
$missingTranslations = ($currentLang !== 'en') ? Language::getMissingTranslations($currentLang) : [];
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Translation Management</h1>
            <p class="text-gray-600">Manage multi-language content and translations</p>
        </div>

        <div class="flex space-x-3">
            <button class="btn btn-outline-primary" onclick="showImportModal()">
                <i class="fa fa-upload"></i> Import Translations
            </button>
            <button class="btn btn-primary" onclick="showAddTranslationModal()">
                <i class="fa fa-plus"></i> Add Translation
            </button>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="rounded-md p-4 <?php
        echo $messageType === 'success' ? 'bg-green-50 border border-green-200' :
             ($messageType === 'error' ? 'bg-red-50 border border-red-200' :
             ($messageType === 'warning' ? 'bg-yellow-50 border border-yellow-200' : 'bg-blue-50 border border-blue-200'));
    ?>">
        <div class="flex">
            <div class="flex-shrink-0">
                <?php
                $iconClass = $messageType === 'success' ? 'fa-check-circle text-green-400' :
                           ($messageType === 'error' ? 'fa-exclamation-circle text-red-400' :
                           ($messageType === 'warning' ? 'fa-exclamation-triangle text-yellow-400' : 'fa-info-circle text-blue-400'));
                ?>
                <i class="fas <?php echo $iconClass; ?>"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium <?php
                    echo $messageType === 'success' ? 'text-green-800' :
                         ($messageType === 'error' ? 'text-red-800' :
                         ($messageType === 'warning' ? 'text-yellow-800' : 'text-blue-800'));
                ?>">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-language text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-500">Active Languages</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $languageStats['active_languages'] ?? 0; ?></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-file-alt text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-500">Total Translations</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo number_format($languageStats['total_translations'] ?? 0); ?></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-video text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-500">Videos with Subtitles</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo number_format($languageStats['videos_with_subtitles'] ?? 0); ?></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-globe text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-500">Translation Coverage</div>
                    <div class="text-2xl font-bold text-gray-900">
                        <?php
                        $coverage = $languageStats['total_translations'] > 0 ?
                            min(100, round(($languageStats['total_translations'] / (count($languages) * 50)) * 100)) : 0;
                        echo $coverage . '%';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Languages Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Languages</h3>
                <div class="space-y-2">
                    <?php foreach ($languages as $lang): ?>
                    <a href="?lang=<?php echo $lang['code']; ?>"
                       class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors <?php echo $currentLang === $lang['code'] ? 'bg-blue-50 border border-blue-200' : ''; ?>">
                        <div class="flex items-center space-x-3">
                            <span class="text-lg"><?php echo htmlspecialchars($lang['flag_emoji']); ?></span>
                            <div>
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($lang['native_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($lang['name']); ?></div>
                            </div>
                        </div>
                        <?php if ($lang['is_rtl']): ?>
                        <span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded">RTL</span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($missingTranslations)): ?>
                <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-yellow-800">Missing Translations</h4>
                            <p class="text-sm text-yellow-700 mt-1">
                                <?php echo count($missingTranslations); ?> translations missing for <?php echo htmlspecialchars($currentLang); ?>
                            </p>
                            <button class="mt-2 text-sm text-yellow-800 underline hover:text-yellow-900" onclick="showMissingTranslationsModal()">
                                View missing
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Translations Editor -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <?php
                            $currentLangInfo = array_filter($languages, function($l) use ($currentLang) {
                                return $l['code'] === $currentLang;
                            });
                            $currentLangInfo = reset($currentLangInfo);
                            echo htmlspecialchars($currentLangInfo['native_name'] ?? $currentLang) . ' Translations';
                            ?>
                        </h3>
                        <div class="flex items-center space-x-4">
                            <select id="categoryFilter" class="form-control form-control-sm" onchange="filterByCategory(this.value)">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo ucfirst(htmlspecialchars($category)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="searchTranslations" class="form-control form-control-sm" placeholder="Search translations..." onkeyup="searchTranslations(this.value)">
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="space-y-4 max-h-96 overflow-y-auto" id="translationsList">
                        <?php foreach ($translations as $key => $text): ?>
                        <div class="translation-item border border-gray-200 rounded-lg p-4" data-key="<?php echo htmlspecialchars($key); ?>">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($key); ?></div>
                                    <textarea class="form-control text-sm translation-text"
                                              rows="2"
                                              data-key="<?php echo htmlspecialchars($key); ?>"
                                              onblur="saveTranslation('<?php echo htmlspecialchars($currentLang); ?>', '<?php echo htmlspecialchars($key); ?>', this.value)"><?php echo htmlspecialchars($text); ?></textarea>
                                </div>
                                <div class="ml-4 flex items-center space-x-2">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="copyTranslationKey('<?php echo htmlspecialchars($key); ?>')">
                                        <i class="fa fa-copy"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTranslation('<?php echo htmlspecialchars($currentLang); ?>', '<?php echo htmlspecialchars($key); ?>')">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($translations)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-language text-4xl mb-4"></i>
                        <p>No translations found for this language.</p>
                        <button class="btn btn-primary mt-4" onclick="showImportModal()">
                            Import Translations
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Translation Section -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Content Translations</h3>
        <p class="text-gray-600 mb-4">Translate video titles, descriptions, and add subtitles for multi-language support.</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 border border-gray-200 rounded-lg hover:border-blue-300 cursor-pointer" onclick="showVideoTranslationModal()">
                <i class="fas fa-video text-3xl text-blue-500 mb-2"></i>
                <h4 class="font-medium text-gray-900">Video Translations</h4>
                <p class="text-sm text-gray-500">Translate titles and descriptions</p>
            </div>

            <div class="text-center p-4 border border-gray-200 rounded-lg hover:border-green-300 cursor-pointer" onclick="showSubtitleModal()">
                <i class="fas fa-closed-captioning text-3xl text-green-500 mb-2"></i>
                <h4 class="font-medium text-gray-900">Subtitles</h4>
                <p class="text-sm text-gray-500">Add subtitle files</p>
            </div>

            <div class="text-center p-4 border border-gray-200 rounded-lg hover:border-purple-300 cursor-pointer" onclick="showBulkTranslationModal()">
                <i class="fas fa-upload text-3xl text-purple-500 mb-2"></i>
                <h4 class="font-medium text-gray-900">Bulk Import</h4>
                <p class="text-sm text-gray-500">Import multiple translations</p>
            </div>
        </div>
    </div>
</div>

<!-- Add Translation Modal -->
<div class="modal fade" id="addTranslationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Add Translation</h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="set_translation">
                    <div class="form-group">
                        <label>Language</label>
                        <select name="language_code" class="form-control" required>
                            <?php foreach ($languages as $lang): ?>
                            <option value="<?php echo $lang['code']; ?>" <?php echo $currentLang === $lang['code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lang['native_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Translation Key</label>
                        <input type="text" name="key" class="form-control" required placeholder="e.g., NAV_HOME">
                    </div>
                    <div class="form-group">
                        <label>Translated Text</label>
                        <textarea name="text" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <option value="general">General</option>
                            <option value="navigation">Navigation</option>
                            <option value="search">Search</option>
                            <option value="categories">Categories</option>
                            <option value="actions">Actions</option>
                            <option value="messages">Messages</option>
                            <option value="donations">Donations</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Translation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Translations Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Import Translations</h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="import_translations">
                    <div class="form-group">
                        <label>Language</label>
                        <select name="language_code" class="form-control" required>
                            <?php foreach ($languages as $lang): ?>
                            <option value="<?php echo $lang['code']; ?>" <?php echo $currentLang === $lang['code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lang['native_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Translations JSON</label>
                        <textarea name="translations_json" class="form-control" rows="10" required
                                  placeholder='{"NAV_HOME": "Home", "SEARCH_BUTTON": "Search", ...}'></textarea>
                        <small class="form-text text-muted">
                            Paste a JSON object with translation keys and values.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import Translations</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal functions
function showAddTranslationModal() {
    $('#addTranslationModal').modal('show');
}

function showImportModal() {
    $('#importModal').modal('show');
}

function showMissingTranslationsModal() {
    // Implementation for showing missing translations
    alert('Missing translations feature coming soon!');
}

function showVideoTranslationModal() {
    alert('Video translation feature coming soon!');
}

function showSubtitleModal() {
    alert('Subtitle management feature coming soon!');
}

function showBulkTranslationModal() {
    $('#importModal').modal('show');
}

// Translation management functions
function saveTranslation(languageCode, key, text) {
    // AJAX call to save translation
    fetch('/lcmtvweb/backend/api/languages/translations', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            language_code: languageCode,
            key: key,
            text: text,
            category: 'general'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            console.log('Translation saved successfully');
        } else {
            console.error('Failed to save translation');
        }
    })
    .catch(error => {
        console.error('Error saving translation:', error);
    });
}

function deleteTranslation(languageCode, key) {
    if (!confirm('Are you sure you want to delete this translation?')) {
        return;
    }

    // For now, just clear the text (full delete would need API endpoint)
    const textarea = document.querySelector(`textarea[data-key="${key}"]`);
    if (textarea) {
        textarea.value = '';
        saveTranslation(languageCode, key, '');
    }
}

function copyTranslationKey(key) {
    navigator.clipboard.writeText(key).then(() => {
        // Could show a toast notification here
        console.log('Translation key copied to clipboard');
    });
}

function searchTranslations(query) {
    const items = document.querySelectorAll('.translation-item');
    const lowerQuery = query.toLowerCase();

    items.forEach(item => {
        const key = item.dataset.key.toLowerCase();
        const text = item.querySelector('textarea').value.toLowerCase();

        if (key.includes(lowerQuery) || text.includes(lowerQuery)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

function filterByCategory(category) {
    const items = document.querySelectorAll('.translation-item');

    if (!category) {
        items.forEach(item => item.style.display = 'block');
        return;
    }

    // This would need category data per translation item
    // For now, just show all
    items.forEach(item => item.style.display = 'block');
}
</script>
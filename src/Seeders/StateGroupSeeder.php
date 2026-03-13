<?php
/**
 * State Group Seeder
 *
 * Creates BuddyBoss groups for each Brazilian state (UF)
 * Runs only on plugin activation
 *
 * @package FortaleceePSE
 * @subpackage Seeders
 */

namespace FortaleceePSE\Core\Seeders;

class StateGroupSeeder {
    /**
     * @var array
     */
    private $states;

    /**
     * Constructor
     *
     * @param array $states Array of states (UF => Name)
     */
    public function __construct($states = []) {
        $this->states = $states;
    }

    /**
     * Seed all state groups
     *
     * Creates or updates BuddyBoss groups for each state
     *
     * @return array Result with 'created', 'updated', 'errors' keys
     */
    public function seed() {
        // Protection: Check if BuddyBoss is loaded
        if (!function_exists('groups_create_group')) {
            error_log('FPSE: BuddyBoss groups API not loaded');
            return [
                'created' => [],
                'updated' => [],
                'errors' => ['BuddyBoss plugin não está ativo'],
            ];
        }

        $created = [];
        $updated = [];
        $errors = [];

        foreach ($this->states as $uf => $stateName) {
            $result = $this->createOrUpdateStateGroup($uf, $stateName);

            if ($result['success']) {
                if ($result['created']) {
                    $created[] = $uf;
                } else {
                    $updated[] = $uf;
                }
            } else {
                $errors[] = "UF {$uf}: {$result['error']}";
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Create or update a state group
     *
     * @param string $uf State code (e.g., 'SP', 'RJ')
     * @param string $stateName State name (e.g., 'São Paulo')
     * @return array Result with 'success', 'created', 'error' keys
     */
    public function createOrUpdateStateGroup($uf, $stateName) {
        $uf = strtoupper(trim($uf));
        $slug = 'estado-' . strtolower($uf);

        // Check if group exists by slug
        $existingGroup = $this->findGroupBySlug($slug);

        $groupData = [
            'slug' => $slug,
            'description' => "Grupo estadual do Fortalece PSE para o estado de {$stateName}",
            'status' => 'private', // Visibilidade privada
            'enable_forum' => 1,
            'date_created' => current_time('mysql'),
        ];

        if ($existingGroup) {
            // Update existing group
            $groupData['group_id'] = $existingGroup->id;

            $result = groups_create_group($groupData);

            if ($result) {
                // Update group avatar
                $this->setStateGroupAvatar($existingGroup->id, $uf);

                return [
                    'success' => true,
                    'created' => false,
                    'group_id' => $existingGroup->id,
                ];
            } else {
                return [
                    'success' => false,
                    'created' => false,
                    'error' => 'Falha ao atualizar grupo',
                ];
            }
        } else {
            // Create new group
            $groupData['name'] = $this->getDefaultStateGroupName($uf);
            $groupId = groups_create_group($groupData);

            if ($groupId) {
                // Set group avatar
                $this->setStateGroupAvatar($groupId, $uf);

                return [
                    'success' => true,
                    'created' => true,
                    'group_id' => $groupId,
                ];
            } else {
                return [
                    'success' => false,
                    'created' => false,
                    'error' => 'Falha ao criar grupo',
                ];
            }
        }
    }

    /**
     * Find group by slug
     *
     * Uses BuddyBoss API to find group by slug
     *
     * @param string $slug Group slug
     * @return object|null Group object or null if not found
     */
    private function findGroupBySlug($slug) {
        if (!function_exists('groups_get_group')) {
            return null;
        }

        // Use BuddyBoss API to get group by slug
        $groups = groups_get_groups([
            'slug' => $slug,
            'per_page' => 1,
            'show_hidden' => true,
        ]);

        if (!empty($groups['groups']) && isset($groups['groups'][0])) {
            return $groups['groups'][0];
        }

        return null;
    }

    /**
     * Get default state group name for new groups.
     *
     * Existing groups keep their current visual label to avoid
     * re-coupling the system to a fixed name pattern.
     *
     * @param string $uf State code
     * @return string
     */
    private function getDefaultStateGroupName($uf) {
        $uf = strtoupper(trim((string) $uf));

        return $uf;
    }

    /**
     * Set state group avatar
     *
     * Uses local asset files from plugin
     *
     * @param int $groupId BuddyBoss group ID
     * @param string $uf State code
     * @return bool True if successful
     */
    private function setStateGroupAvatar($groupId, $uf) {
        if (!function_exists('groups_avatar_upload_dir')) {
            return false;
        }

        $uf = strtolower($uf);
        $flagPath = $this->getStateFlagPath($uf);

        if (!$flagPath || !file_exists($flagPath)) {
            error_log("FPSE: Bandeira não encontrada para UF {$uf}: {$flagPath}");
            return false;
        }

        // Get avatar upload directory
        $avatarDir = groups_avatar_upload_dir('group-avatar-images', $groupId);

        if (!$avatarDir || is_wp_error($avatarDir)) {
            return false;
        }

        // Copy flag to avatar directory
        $destination = $avatarDir['path'] . '/group-avatar-' . $groupId . '.png';

        if (!copy($flagPath, $destination)) {
            error_log("FPSE: Falha ao copiar bandeira para {$destination}");
            return false;
        }

        // Update group avatar metadata
        groups_update_groupmeta($groupId, 'avatar_file', basename($destination));

        return true;
    }

    /**
     * Get state flag file path
     *
     * Looks for flag in assets/flags/{uf}.png
     *
     * @param string $uf State code (lowercase)
     * @return string|null File path or null if not found
     */
    private function getStateFlagPath($uf) {
        $uf = strtolower($uf);
        $pluginPath = FPSE_CORE_PATH;
        $flagPath = $pluginPath . 'assets/flags/' . $uf . '.png';

        if (file_exists($flagPath)) {
            return $flagPath;
        }

        // Try with uppercase
        $flagPath = $pluginPath . 'assets/flags/' . strtoupper($uf) . '.png';
        if (file_exists($flagPath)) {
            return $flagPath;
        }

        return null;
    }

    /**
     * Get all state groups
     *
     * @return array Array of group objects
     */
    public function getAllStateGroups() {
        if (!function_exists('groups_get_groups')) {
            return [];
        }

        $groups = [];

        foreach ($this->getExpectedStateGroupSlugs() as $slug) {
            $group = $this->findGroupBySlug($slug);
            if ($group && !empty($group->id)) {
                $groups[$group->id] = $group;
            }
        }

        return array_values($groups);
    }

    /**
     * Get group by UF
     *
     * @param string $uf State code
     * @return object|null Group object or null
     */
    public function getGroupByUF($uf) {
        $uf = strtolower(trim($uf));
        $slug = 'estado-' . $uf;

        return $this->findGroupBySlug($slug);
    }

    /**
     * Count existing state groups using expected state slugs.
     *
     * @return int
     */
    public function countStateGroups() {
        return count($this->getAllStateGroups());
    }

    /**
     * Rename existing state groups to the simple UF label while preserving
     * stable identifiers such as slug and group ID.
     *
     * @return array
     */
    public function syncStateGroupVisualNamesToUfLabels() {
        $result = [
            'renamed' => [],
            'already_standard' => [],
            'missing' => [],
            'errors' => [],
        ];

        if (!function_exists('groups_create_group')) {
            $result['errors'][] = 'BuddyBoss groups API not loaded';
            return $result;
        }

        foreach (array_keys($this->states) as $uf) {
            $uf = strtoupper(trim((string) $uf));
            if ($uf === '') {
                continue;
            }

            $group = $this->getGroupByUF($uf);
            if (!$group || empty($group->id)) {
                $result['missing'][] = $uf;
                continue;
            }

            $desiredName = $this->getDefaultStateGroupName($uf);
            $currentName = isset($group->name) ? trim((string) $group->name) : '';

            if ($currentName === $desiredName) {
                $result['already_standard'][] = $uf;
                continue;
            }

            $updated = groups_create_group([
                'group_id' => (int) $group->id,
                'name' => $desiredName,
                'slug' => isset($group->slug) ? (string) $group->slug : 'estado-' . strtolower($uf),
                'description' => isset($group->description) ? (string) $group->description : '',
                'status' => isset($group->status) ? (string) $group->status : 'private',
                'enable_forum' => !empty($group->enable_forum) ? 1 : 0,
            ]);

            if ($updated) {
                $result['renamed'][] = [
                    'uf' => $uf,
                    'from' => $currentName,
                    'to' => $desiredName,
                    'group_id' => (int) $group->id,
                    'slug' => isset($group->slug) ? (string) $group->slug : 'estado-' . strtolower($uf),
                ];
            } else {
                $result['errors'][] = "Falha ao renomear grupo da UF {$uf}";
            }
        }

        return $result;
    }

    /**
     * Return the canonical state group slugs configured for the environment.
     *
     * @return array
     */
    private function getExpectedStateGroupSlugs() {
        $slugs = [];

        foreach (array_keys($this->states) as $uf) {
            $uf = strtoupper(trim((string) $uf));
            if ($uf === '') {
                continue;
            }

            $slugs[] = 'estado-' . strtolower($uf);
        }

        return array_values(array_unique($slugs));
    }
}

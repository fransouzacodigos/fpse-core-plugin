<?php
/**
 * Profile Resolver Service
 *
 * Validates and resolves user profiles
 *
 * @package FortaleceePSE
 * @subpackage Services
 */

namespace FortaleceePSE\Core\Services;

use FortaleceePSE\Core\Plugin;

class ProfileResolver {
    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin Main plugin instance
     */
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Get all available profiles
     *
     * @return array Profiles from config
     */
    public function getAllProfiles() {
        return $this->plugin->getConfig('profiles', []);
    }

    /**
     * Get profile by identifier
     *
     * @param string $profileId Profile identifier
     * @return array|null Profile data or null if not found
     */
    public function getProfile($profileId) {
        $profiles = $this->getAllProfiles();
        $profileId = sanitize_text_field($profileId);

        return $profiles[$profileId] ?? null;
    }

    /**
     * Validate a profile identifier
     *
     * @param string $profileId Profile identifier to validate
     * @return bool True if profile exists
     */
    public function isValidProfile($profileId) {
        return $this->getProfile($profileId) !== null;
    }

    /**
     * Get profile label
     *
     * @param string $profileId Profile identifier
     * @return string Profile label or empty string if not found
     */
    public function getProfileLabel($profileId) {
        $profile = $this->getProfile($profileId);
        return $profile['label'] ?? '';
    }

    /**
     * Get profile category
     *
     * @param string $profileId Profile identifier
     * @return string Profile category or empty string if not found
     */
    public function getProfileCategory($profileId) {
        $profile = $this->getProfile($profileId);
        return $profile['category'] ?? '';
    }

    /**
     * Get profile description
     *
     * @param string $profileId Profile identifier
     * @return string Profile description or empty string if not found
     */
    public function getProfileDescription($profileId) {
        $profile = $this->getProfile($profileId);
        return $profile['description'] ?? '';
    }

    /**
     * Get profile-specific fields
     *
     * Fields that should be present for this profile
     *
     * @param string $profileId Profile identifier
     * @return array Field names required for this profile
     */
    public function getProfileSpecificFields($profileId) {
        $profile = $this->getProfile($profileId);
        return $profile['specific_fields'] ?? [];
    }

    /**
     * Get profiles by category
     *
     * @param string $category Category name
     * @return array Profiles in the category
     */
    public function getProfilesByCategory($category) {
        $profiles = $this->getAllProfiles();
        $category = sanitize_text_field($category);
        $result = [];

        foreach ($profiles as $id => $profile) {
            if (($profile['category'] ?? '') === $category) {
                $result[$id] = $profile;
            }
        }

        return $result;
    }

    /**
     * Get all available categories
     *
     * @return array Unique categories from all profiles
     */
    public function getAllCategories() {
        $profiles = $this->getAllProfiles();
        $categories = [];

        foreach ($profiles as $profile) {
            $category = $profile['category'] ?? 'other';
            if (!in_array($category, $categories)) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    /**
     * Validate profile-specific fields
     *
     * Checks if all required profile-specific fields are present in the data
     *
     * @param string $profileId Profile identifier
     * @param array $data Data to validate
     * @return array Validation result with keys: valid (bool), missing (array)
     */
    public function validateProfileSpecificFields($profileId, $data = []) {
        $required = $this->getProfileSpecificFields($profileId);
        $missing = [];

        foreach ($required as $field) {
            // Check if field is missing or empty (but 0 and false are valid values)
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[] = $field;
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing,
            'required' => $required,
        ];
    }

    /**
     * Validate complete profile data
     *
     * Checks profile exists and all specific fields are provided
     *
     * @param string $profileId Profile identifier
     * @param array $data Data to validate
     * @return array Validation result with keys: valid (bool), errors (array)
     */
    public function validateProfile($profileId, $data = []) {
        $errors = [];

        // Check if profile exists
        if (!$this->isValidProfile($profileId)) {
            $errors[] = "Perfil '{$profileId}' não existe";
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        // Validate profile-specific fields
        $fieldValidation = $this->validateProfileSpecificFields($profileId, $data);
        if (!$fieldValidation['valid']) {
            $errors[] = "Campos específicos do perfil faltando: " . implode(', ', $fieldValidation['missing']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get field metadata for a profile
     *
     * Returns detailed field information from report_fields.php config
     *
     * @param string $profileId Profile identifier
     * @return array Field metadata for this profile
     */
    public function getProfileFieldMetadata($profileId) {
        $fields = $this->getProfileSpecificFields($profileId);
        $reportFields = $this->plugin->getConfig('report_fields', []);
        $metadata = [];

        foreach ($fields as $fieldName) {
            if (isset($reportFields[$fieldName])) {
                $metadata[$fieldName] = $reportFields[$fieldName];
            }
        }

        return $metadata;
    }
}

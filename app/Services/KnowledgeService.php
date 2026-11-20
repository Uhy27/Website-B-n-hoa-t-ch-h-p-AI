<?php
declare(strict_types=1);

/**
 * KnowledgeService
 * Grounded Knowledge Base Service for Flower Meanings, Care Guides & Store Policies
 */
class KnowledgeService {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? getDBConnection();
    }

    /**
     * Search knowledge items by keyword and optional category
     */
    public function search(string $keyword, ?string $category = null): array {
        $kw = trim($keyword);
        if ($kw === '') {
            return [];
        }

        $kwClean = trim(str_replace('-', ' ', $kw));

        $sql = "SELECT id, category, title, keywords, content 
                FROM ai_knowledge 
                WHERE is_active = 1";
        $params = [];

        if ($category !== null && in_array($category, ['meaning', 'care', 'faq', 'policy'], true)) {
            $sql .= " AND category = :category";
            $params[':category'] = $category;
        }

        $sql .= " AND (title LIKE :kw1 OR keywords LIKE :kw2 OR content LIKE :kw3 OR keywords LIKE :kw4 OR title LIKE :kw5) LIMIT 5";
        $params[':kw1'] = '%' . $kw . '%';
        $params[':kw2'] = '%' . $kw . '%';
        $params[':kw3'] = '%' . $kw . '%';
        $params[':kw4'] = '%' . $kwClean . '%';
        $params[':kw5'] = '%' . $kwClean . '%';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[KnowledgeService Search Error] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get flower meaning by name
     */
    public function getFlowerMeaning(?string $flowerName): ?array {
        if ($flowerName === null || trim($flowerName) === '') {
            return null;
        }
        $results = $this->search($flowerName, 'meaning');
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Get flower care guide
     */
    public function getCareGuide(?string $flowerOrType = ''): ?array {
        if (!empty($flowerOrType)) {
            $res = $this->search($flowerOrType, 'care');
            if (!empty($res)) return $res[0];
        }
        // Fallback to general cut flower care guide
        $res = $this->search('cắt cành', 'care');
        if (!empty($res)) return $res[0];
        $all = $this->search('chăm sóc', 'care');
        return !empty($all) ? $all[0] : null;
    }

    /**
     * Get shop policy or FAQ (delivery, refund, inspection)
     */
    public function getPolicy(string $topic): ?array {
        $res = $this->search($topic, 'policy');
        return !empty($res) ? $res[0] : null;
    }
}

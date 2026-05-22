<?php
/**
 * Product Manager Class
 * Handles CRUD for products, variations, and FIFO inventory.
 */

class ProductManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new product
     */
    public function createProduct($data) {
        $sql = "INSERT INTO products (category_id, name, slug, description, base_price, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['category_id'] ?? null,
            $data['name'],
            $data['slug'] ?? $this->generateSlug($data['name']),
            $data['description'] ?? null,
            $data['base_price'],
            $data['is_active'] ?? 1
        ];

        $this->db->query($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Add a variation to a product
     */
    public function addVariation($productId, $data) {
        $sql = "INSERT INTO product_variations (product_id, sku, name_modifier, price_override, wholesale_price, retail_min_qty, wholesale_min_qty, qty_in_box, box_weight) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $productId,
            $data['sku'],
            $data['name_modifier'] ?? null,
            $data['price_override'] ?? null,
            $data['wholesale_price'] ?? null,
            $data['retail_min_qty'] ?? 1,
            $data['wholesale_min_qty'] ?? 1,
            $data['qty_in_box'] ?? 1,
            $data['box_weight'] ?? 0.00
        ];

        $this->db->query($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Add an inventory batch (FIFO)
     */
    public function addInventoryBatch($variationId, $data) {
        $sql = "INSERT INTO inventory_batches (product_variation_id, quantity_initial, quantity_remaining, cost_price) 
                VALUES (?, ?, ?, ?)";
        
        $params = [
            $variationId,
            $data['quantity'],
            $data['quantity'], // quantity_remaining initially equals quantity_initial
            $data['cost_price']
        ];

        $this->db->query($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Get a product with its variations and stock levels
     */
    public function getProduct($id) {
        $productSql = "SELECT * FROM products WHERE id = ?";
        $product = $this->db->query($productSql, [$id])->fetch();

        if ($product) {
            $variationSql = "SELECT v.*, SUM(b.quantity_remaining) as total_stock 
                             FROM product_variations v 
                             LEFT JOIN inventory_batches b ON v.id = b.product_variation_id 
                             WHERE v.product_id = ? 
                             GROUP BY v.id";
            $product['variations'] = $this->db->query($variationSql, [$id])->fetchAll();
        }

        return $product;
    }

    /**
     * Get all active products with primary images and default variation
     */
    public function getAllProducts($categoryId = null) {
        $sql = "SELECT p.*, i.file_path as primary_image, v.id as default_variation_id, 
                v.wholesale_price, v.retail_min_qty, v.wholesale_min_qty,
                (SELECT SUM(quantity_remaining) FROM inventory_batches b JOIN product_variations v2 ON b.product_variation_id = v2.id WHERE v2.product_id = p.id) as total_stock 
                FROM products p 
                LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = 1 
                LEFT JOIN product_variations v ON p.id = v.product_id 
                WHERE p.is_active = 1";
        
        $params = [];
        if ($categoryId) {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }

        $sql .= " GROUP BY p.id ORDER BY p.created_at DESC";
        
        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * Get a product by slug with variations and stock
     */
    public function getProductBySlug($slug) {
        $productSql = "SELECT * FROM products WHERE slug = ?";
        $product = $this->db->query($productSql, [$slug])->fetch();

        if ($product) {
            $variationSql = "SELECT v.*, SUM(b.quantity_remaining) as total_stock 
                             FROM product_variations v 
                             LEFT JOIN inventory_batches b ON v.id = b.product_variation_id 
                             WHERE v.product_id = ? 
                             GROUP BY v.id";
            $product['variations'] = $this->db->query($variationSql, [$product['id']])->fetchAll();
        }

        return $product;
    }

    /**
     * Get all images for a product
     */
    public function getImages($productId) {
        $sql = "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC";
        return $this->db->query($sql, [$productId])->fetchAll();
    }

    /**
     * Add an image to a product
     */
    public function addImage($productId, $filePath, $isPrimary = 0) {
        // If this is primary, unset other primary images
        if ($isPrimary) {
            $this->db->query("UPDATE product_images SET is_primary = 0 WHERE product_id = ?", [$productId]);
        }
        
        $sql = "INSERT INTO product_images (product_id, file_path, is_primary) VALUES (?, ?, ?)";
        return $this->db->query($sql, [$productId, $filePath, $isPrimary]);
    }

    /**
     * Bulk Update via CSV
     */
    public function bulkUpdateCSV($csvData) {
        $results = ['success' => 0, 'errors' => []];
        
        foreach ($csvData as $index => $row) {
            $sku = $row['sku'] ?? null;
            if (!$sku) {
                $results['errors'][] = "Row " . ($index + 1) . ": SKU missing.";
                continue;
            }

            // Find variation by SKU
            $var = $this->db->query("SELECT id FROM product_variations WHERE sku = ?", [$sku])->fetch();
            if (!$var) {
                $results['errors'][] = "Row " . ($index + 1) . ": SKU '$sku' not found.";
                continue;
            }

            $variationId = $var['id'];

            try {
                $this->db->beginTransaction();

                // 1. Update Prices and Specs if provided
                $updates = [];
                $params = [];
                
                $map = [
                    'price' => 'price_override',
                    'wholesale_price' => 'wholesale_price',
                    'retail_min' => 'retail_min_qty',
                    'wholesale_min' => 'wholesale_min_qty',
                    'box_qty' => 'qty_in_box',
                    'box_weight' => 'box_weight'
                ];

                foreach ($map as $csvKey => $dbCol) {
                    if (isset($row[$csvKey]) && $row[$csvKey] !== '') {
                        $updates[] = "$dbCol = ?";
                        $params[] = $row[$csvKey];
                    }
                }

                if (!empty($updates)) {
                    $sql = "UPDATE product_variations SET " . implode(', ', $updates) . " WHERE id = ?";
                    $params[] = $variationId;
                    $this->db->query($sql, $params);
                }

                // 2. Add Stock if provided
                if (!empty($row['stock_qty'])) {
                    $qty = (int)$row['stock_qty'];
                    $cost = (float)($row['cost_price'] ?? 0);
                    $this->addInventoryBatch($variationId, ['quantity' => $qty, 'cost_price' => $cost]);
                }

                $this->db->commit();
                $results['success']++;
            } catch (Exception $e) {
                $this->db->rollBack();
                $results['errors'][] = "Row " . ($index + 1) . ": Update failed for $sku.";
            }
        }

        return $results;
    }

    /**
     * Helper: Generate Slug
     */
    private function generateSlug($text) {
        $text = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text)));
        return $text;
    }

    /**
     * Helper: Generate SKU
     */
    public function generateSKU($categoryPrefix, $productName, $modifier = '') {
        $cleanName = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $productName), 0, 5));
        $sku = strtoupper($categoryPrefix) . '-' . $cleanName;
        if ($modifier) {
            $sku .= '-' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $modifier));
        }
        return $sku . '-' . rand(100, 999);
    }
}

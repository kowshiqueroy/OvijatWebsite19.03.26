<?php
/**
 * Cart Manager Class
 * Handles server-side validation and synchronization of user carts.
 */

class CartManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Sync Client Cart with Server Truth
     * @param array $clientCart Array of ['variation_id' => ID, 'quantity' => Qty]
     * @return array Verified cart with prices, subtotal, and stock warnings.
     */
    public function syncCart($clientCart) {
        $syncedItems = [];
        $subtotal = 0;
        $totalWeight = 0;
        $userRole = $_SESSION['user_role'] ?? 'retail';

        foreach ($clientCart as $item) {
            $variationId = (int)$item['variation_id'];
            $requestedQty = (int)$item['quantity'];

            // Fetch variation, product, and stock levels
            $sql = "SELECT v.*, p.name as product_name, p.base_price, 
                           SUM(b.quantity_remaining) as total_stock
                    FROM product_variations v
                    JOIN products p ON v.product_id = p.id
                    LEFT JOIN inventory_batches b ON v.id = b.product_variation_id
                    WHERE v.id = ?
                    GROUP BY v.id";
            
            $data = $this->db->query($sql, [$variationId])->fetch();

            if ($data) {
                // 1. Determine Price (Wholesale vs Retail)
                $price = $data['price_override'] ?? $data['base_price'];
                if ($userRole === 'wholesale' && !empty($data['wholesale_price'])) {
                    $price = $data['wholesale_price'];
                }

                // 2. Validate Min Qty
                $minQty = ($userRole === 'wholesale') ? $data['wholesale_min_qty'] : $data['retail_min_qty'];
                $actualQty = max($requestedQty, $minQty);

                // 3. Check Stock
                $totalStock = (int)($data['total_stock'] ?? 0);
                $actualQty = min($actualQty, $totalStock);
                
                if ($actualQty > 0) {
                    $itemTotal = $price * $actualQty;
                    $subtotal += $itemTotal;

                    // 4. Calculate Weight (Weight per box * number of boxes)
                    $boxes = ceil($actualQty / $data['qty_in_box']);
                    $itemWeight = $boxes * $data['box_weight'];
                    $totalWeight += $itemWeight;

                    $syncedItems[] = [
                        'variation_id' => $variationId,
                        'sku' => $data['sku'],
                        'name' => $data['product_name'] . ($data['name_modifier'] ? " ({$data['name_modifier']})" : ""),
                        'unit_price' => $price,
                        'quantity' => $actualQty,
                        'requested_quantity' => $requestedQty,
                        'total_price' => $itemTotal,
                        'total_weight' => $itemWeight,
                        'in_stock' => $totalStock >= $actualQty,
                        'min_qty_met' => $actualQty >= $minQty
                    ];
                }
            }
        }

        return [
            'items' => $syncedItems,
            'subtotal' => $subtotal,
            'total_weight' => $totalWeight
        ];
    }

    /**
     * Calculate Final Order Totals
     */
    public function calculateTotals($subtotal, $weight = 0, $stateCode = null) {
        $shipping = 0;
        $taxRate = 0.00; // Default

        // 1. Calculate Shipping based on Weight
        if ($subtotal > 0) {
            $rate = $this->db->query("SELECT rate FROM shipping_rates WHERE ? >= min_weight AND ? <= max_weight LIMIT 1", [$weight, $weight])->fetchColumn();
            $shipping = $rate !== false ? (float)$rate : 0;
        }

        // 2. Calculate Tax based on State
        if ($stateCode) {
            $tRate = $this->db->query("SELECT tax_rate FROM state_taxes WHERE state_code = ? LIMIT 1", [strtoupper($stateCode)])->fetchColumn();
            if ($tRate !== false) $taxRate = (float)$tRate;
        }

        $tax = $subtotal * $taxRate;
        $grandTotal = $subtotal + $shipping + $tax;

        return [
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shipping, 2),
            'tax' => round($tax, 2),
            'total' => round($grandTotal, 2),
            'weight' => $weight,
            'tax_rate' => $taxRate
        ];
    }
}

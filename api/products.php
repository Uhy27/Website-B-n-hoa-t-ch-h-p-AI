<?php
declare(strict_types=1);
/** GET /api/products.php - list products with filters */
require_once __DIR__ . '/../core/bootstrap.php';

api_only_method('GET');
try {
    $pdo = getDBConnection();
    $category = trim((string)($_GET['category'] ?? 'all'));
    $price    = trim((string)($_GET['price'] ?? 'all'));
    $color    = trim((string)($_GET['color'] ?? 'all'));
    $q        = trim((string)($_GET['q'] ?? ''));
    $sort     = trim((string)($_GET['sort'] ?? 'popular'));

    $where = ['p.is_active = 1'];
    $params = [];
    if ($category !== '' && $category !== 'all') { $where[] = 'c.slug = :category'; $params[':category'] = $category; }
    if ($price === 'under-500') $where[] = 'p.price < 500000';
    elseif ($price === '500-1000') $where[] = '(p.price >= 500000 AND p.price <= 1000000)';
    elseif ($price === '1000-2000') $where[] = '(p.price > 1000000 AND p.price <= 2000000)';
    elseif ($price === 'above-2000') $where[] = 'p.price > 2000000';
    if ($color !== '' && $color !== 'all') { $where[] = 'p.color = :color'; $params[':color'] = $color; }
    if ($q !== '') {
        $where[] = '(p.name LIKE :kw1 OR p.primary_flower LIKE :kw2 OR p.color_name LIKE :kw3 OR p.meaning LIKE :kw4 OR c.name LIKE :kw5 OR p.sku LIKE :kw6)';
        $kw = '%'.$q.'%';
        foreach ([':kw1',':kw2',':kw3',':kw4',':kw5',':kw6'] as $k) $params[$k] = $kw;
    }
    $orderBy = 'p.reviews_count DESC';
    if ($sort === 'price-asc') $orderBy = 'p.price ASC';
    elseif ($sort === 'price-desc') $orderBy = 'p.price DESC';
    elseif ($sort === 'rating') $orderBy = 'p.rating DESC, p.reviews_count DESC';

    $sql = "SELECT p.id,p.category_id,p.sku,p.name,p.slug,p.price,p.sale_price,p.cost_price,
                   p.stock_quantity,p.primary_flower,p.color,p.color_name,p.size_dimensions,
                   p.meaning,p.tags,p.thumbnail AS image,p.rating,p.reviews_count AS reviewsCount,
                   c.name AS categoryName,c.slug AS categorySlug
            FROM products p INNER JOIN categories c ON p.category_id = c.id
            WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $products = $stmt->fetchAll();
    foreach ($products as &$p) {
        $p['id']=(int)$p['id']; $p['category_id']=(int)$p['category_id'];
        $p['price']=(float)$p['price']; $p['originalPrice']=$p['sale_price']? (float)$p['sale_price']:(float)$p['price'];
        $p['cost_price'] = isset($p['cost_price']) ? (float)$p['cost_price'] : 0.0;
        $p['stock_quantity']=(int)$p['stock_quantity']; $p['rating']=(float)$p['rating']; $p['reviewsCount']=(int)$p['reviewsCount'];
        $p['tags']=!empty($p['tags']) ? array_values(array_filter(array_map('trim', explode(',', (string)$p['tags'])))) : [];
        $p['flowerTypes']=$p['primary_flower']; $p['size']=$p['size_dimensions'];
    }
    json_response(['success'=>true,'count'=>count($products),'data'=>$products]);
} catch (Throwable $e) {
    error_log('[Products API Error] '.$e->getMessage());
    json_response(['success'=>false,'message'=>'Lỗi truy vấn danh sách sản phẩm.'],500);
}
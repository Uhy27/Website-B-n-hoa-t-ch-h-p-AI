<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/bootstrap.php';
require_admin_auth();

function bloomora_slugify(string $text): string {
    $text = mb_strtolower(trim($text), 'UTF-8');
    $map = [
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d'
    ];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', (string)$text);
    return trim((string)$text, '-');
}

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = trim((string)($_GET['search'] ?? ''));
        $categoryId = (int)($_GET['category_id'] ?? 0);
        $status = trim((string)($_GET['status'] ?? ''));

        $where = []; $params = [];
        if ($search !== '') { $where[] = '(p.name LIKE :s1 OR p.sku LIKE :s2)'; $params[':s1']="%$search%"; $params[':s2']="%$search%"; }
        if ($categoryId > 0) { $where[] = 'p.category_id = :cid'; $params[':cid']=$categoryId; }
        if ($status === 'active') $where[] = 'p.is_active = 1';
        elseif ($status === 'inactive') $where[] = 'p.is_active = 0';
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $cst = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql");
        $cst->execute($params); $total = (int)$cst->fetchColumn();

        $sql = "SELECT p.id,p.category_id,c.name AS categoryName,p.sku,p.name,p.slug,p.price,p.sale_price,p.cost_price,p.stock_quantity,
                       p.primary_flower,p.color,p.color_name,p.size_dimensions,p.meaning,p.tags,p.thumbnail,p.is_featured,p.is_active,
                       p.rating,p.reviews_count,p.created_at,
                       CASE WHEN p.price>0 THEN ROUND(((p.price - COALESCE(p.cost_price,0))/p.price)*100,2) ELSE 0 END AS profit_margin
                FROM products p LEFT JOIN categories c ON c.id=p.category_id
                $whereSql ORDER BY p.id DESC LIMIT :limit OFFSET :offset";
        $st = $pdo->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':limit',$limit,PDO::PARAM_INT); $st->bindValue(':offset',$offset,PDO::PARAM_INT);
        $st->execute(); $items = $st->fetchAll();
        json_response(['success'=>true,'data'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'items'=>$items]]);
    }

    if ($method === 'POST') {
        $in = get_json_input();
        foreach (['name','category_id','sku','price','stock_quantity','thumbnail'] as $f) {
            if (!isset($in[$f]) || trim((string)$in[$f]) === '') json_response(['success'=>false,'message'=>"Trường '$f' là bắt buộc."],400);
        }
        $sku = trim((string)$in['sku']);
        $chk = $pdo->prepare('SELECT COUNT(*) FROM products WHERE sku=:sku'); $chk->execute([':sku'=>$sku]);
        if ((int)$chk->fetchColumn() > 0) json_response(['success'=>false,'message'=>'Mã SKU đã tồn tại.'],409);

        $baseSlug = trim((string)($in['slug'] ?? '')) !== '' ? trim((string)$in['slug']) : (string)$in['name'];
        $slug = bloomora_slugify($baseSlug); if ($slug === '') $slug = 'san-pham';
        $orig = $slug; $i=1;
        while (true) { $s=$pdo->prepare('SELECT COUNT(*) FROM products WHERE slug=:s'); $s->execute([':s'=>$slug]); if((int)$s->fetchColumn()===0) break; $slug=$orig.'-'.$i++; }

        $sql = "INSERT INTO products (name,category_id,sku,slug,price,cost_price,sale_price,stock_quantity,primary_flower,color,color_name,size_dimensions,meaning,tags,thumbnail,is_featured,is_active,rating)
                VALUES (:name,:category_id,:sku,:slug,:price,:cost_price,:sale_price,:stock_quantity,:primary_flower,:color,:color_name,:size_dimensions,:meaning,:tags,:thumbnail,:is_featured,:is_active,:rating)";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':name'=>trim((string)$in['name']), ':category_id'=>(int)$in['category_id'], ':sku'=>$sku, ':slug'=>$slug,
            ':price'=>(float)$in['price'], ':cost_price'=>(float)($in['cost_price'] ?? 0), ':sale_price'=>isset($in['sale_price']) && $in['sale_price']!=='' ? (float)$in['sale_price'] : null,
            ':stock_quantity'=>(int)$in['stock_quantity'], ':primary_flower'=>$in['primary_flower'] ?? null, ':color'=>$in['color'] ?? null,
            ':color_name'=>$in['color_name'] ?? null, ':size_dimensions'=>$in['size_dimensions'] ?? null, ':meaning'=>$in['meaning'] ?? null,
            ':tags'=>$in['tags'] ?? null, ':thumbnail'=>trim((string)$in['thumbnail']), ':is_featured'=>(int)($in['is_featured'] ?? 1),
            ':is_active'=>(int)($in['is_active'] ?? 1), ':rating'=>(float)($in['rating'] ?? 5.0)
        ]);
        json_response(['success'=>true,'message'=>'Tạo sản phẩm thành công.','data'=>['id'=>(int)$pdo->lastInsertId()]],201);
    }

    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_response(['success'=>false,'message'=>'Thiếu id sản phẩm.'],400);
        $chk = $pdo->prepare('SELECT id FROM products WHERE id=:id'); $chk->execute([':id'=>$id]);
        if (!$chk->fetch()) json_response(['success'=>false,'message'=>'Sản phẩm không tồn tại.'],404);
        $in = get_json_input(); if (empty($in)) json_response(['success'=>false,'message'=>'Không có dữ liệu cập nhật.'],400);
        $allowed=['name','category_id','sku','slug','price','cost_price','sale_price','stock_quantity','primary_flower','color','color_name','size_dimensions','meaning','tags','thumbnail','is_featured','is_active','rating'];
        $sets=[]; $params=[':id'=>$id];
        foreach($allowed as $f){ if(array_key_exists($f,$in)){ $sets[]="`$f`=:$f"; $params[":$f"]=$in[$f]; } }
        if(!$sets) json_response(['success'=>false,'message'=>'Không có trường hợp lệ để cập nhật.'],400);
        if(isset($in['sku'])){
            $s=$pdo->prepare('SELECT COUNT(*) FROM products WHERE sku=:sku AND id!=:id'); $s->execute([':sku'=>trim((string)$in['sku']),':id'=>$id]);
            if((int)$s->fetchColumn()>0) json_response(['success'=>false,'message'=>'Mã SKU đã tồn tại cho sản phẩm khác.'],409);
        }
        $pdo->prepare('UPDATE products SET '.implode(',',$sets).' WHERE id=:id')->execute($params);
        json_response(['success'=>true,'message'=>'Cập nhật sản phẩm thành công.']);
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_response(['success'=>false,'message'=>'Thiếu id sản phẩm.'],400);
        $st=$pdo->prepare('UPDATE products SET is_active=0 WHERE id=:id'); $st->execute([':id'=>$id]);
        json_response(['success'=>true,'message'=>'Đã vô hiệu hóa sản phẩm.']);
    }

    json_response(['success'=>false,'message'=>'Phương thức HTTP không được hỗ trợ.'],405);
} catch (Throwable $e) {
    error_log('[Admin Products] '.$e->getMessage());
    json_response(['success'=>false,'message'=>'Lỗi hệ thống khi xử lý sản phẩm.'],500);
}
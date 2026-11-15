<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/bootstrap.php';
require_admin_auth();
try {
    $pdo=getDBConnection(); $method=$_SERVER['REQUEST_METHOD']??'GET';
    if($method==='GET'){
        $rows=$pdo->query("SELECT c.id,c.name,c.slug,c.icon,c.badge,c.display_order,c.is_active,COUNT(p.id) AS product_count
                           FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.is_active=1
                           GROUP BY c.id ORDER BY c.display_order ASC,c.id ASC")->fetchAll();
        json_response(['success'=>true,'data'=>$rows]);
    }
    if($method==='POST'){
        $in=get_json_input();
        if(trim((string)($in['name']??''))==='') json_response(['success'=>false,'message'=>'Tên danh mục là bắt buộc.'],400);
        $slug=trim((string)($in['slug']??'')); if($slug==='') json_response(['success'=>false,'message'=>'Slug là bắt buộc.'],400);
        $chk=$pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug=:s'); $chk->execute([':s'=>$slug]);
        if((int)$chk->fetchColumn()>0) json_response(['success'=>false,'message'=>'Slug danh mục đã tồn tại.'],409);
        $st=$pdo->prepare("INSERT INTO categories (name,slug,icon,badge,display_order,is_active) VALUES (:n,:s,:i,:b,:o,:a)");
        $st->execute([':n'=>trim((string)$in['name']),':s'=>$slug,':i'=>trim((string)($in['icon']??'ph-flower-lotus')),':b'=>$in['badge']??null,':o'=>(int)($in['display_order']??0),':a'=>(int)($in['is_active']??1)]);
        json_response(['success'=>true,'message'=>'Tạo danh mục thành công.','data'=>['id'=>(int)$pdo->lastInsertId()]],201);
    }
    if($method==='PUT'){
        $id=(int)($_GET['id']??0); if($id<=0) json_response(['success'=>false,'message'=>'Thiếu id danh mục.'],400);
        $in=get_json_input(); $allowed=['name','slug','icon','badge','display_order','is_active']; $sets=[];$params=[':id'=>$id];
        foreach($allowed as $f){ if(array_key_exists($f,$in)){ $sets[]="`$f`=:$f"; $params[":$f"]=$in[$f]; } }
        if(!$sets) json_response(['success'=>false,'message'=>'Không có trường hợp lệ.'],400);
        if(isset($in['slug'])){ $c=$pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug=:s AND id!=:id'); $c->execute([':s'=>trim((string)$in['slug']),':id'=>$id]); if((int)$c->fetchColumn()>0) json_response(['success'=>false,'message'=>'Slug đã tồn tại.'],409);} 
        $pdo->prepare('UPDATE categories SET '.implode(',',$sets).' WHERE id=:id')->execute($params);
        json_response(['success'=>true,'message'=>'Cập nhật danh mục thành công.']);
    }
    if($method==='DELETE'){
        $id=(int)($_GET['id']??0); if($id<=0) json_response(['success'=>false,'message'=>'Thiếu id danh mục.'],400);
        $pdo->prepare('UPDATE categories SET is_active=0 WHERE id=:id')->execute([':id'=>$id]);
        json_response(['success'=>true,'message'=>'Đã vô hiệu hóa danh mục.']);
    }
    json_response(['success'=>false,'message'=>'Phương thức HTTP không được hỗ trợ.'],405);
} catch (Throwable $e){ error_log('[Admin Categories] '.$e->getMessage()); json_response(['success'=>false,'message'=>'Lỗi hệ thống khi xử lý danh mục.'],500);} 
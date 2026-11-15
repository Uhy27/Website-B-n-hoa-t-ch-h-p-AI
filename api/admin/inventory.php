<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/bootstrap.php';
require_admin_auth();
try {
    $pdo=getDBConnection(); $method=$_SERVER['REQUEST_METHOD']??'GET';
    if($method==='GET'){
        if(($_GET['logs']??'0')==='1'){
            $pid=(int)($_GET['product_id']??0); if($pid<=0) json_response(['success'=>false,'message'=>'Thiếu product_id.'],400);
            $p=$pdo->prepare('SELECT id,name,sku FROM products WHERE id=:id'); $p->execute([':id'=>$pid]); $prod=$p->fetch();
            if(!$prod) json_response(['success'=>false,'message'=>'Sản phẩm không tồn tại.'],404);
            $page=max(1,(int)($_GET['page']??1)); $limit=max(1,min(100,(int)($_GET['limit']??50))); $off=($page-1)*$limit;
            $c=$pdo->prepare('SELECT COUNT(*) FROM inventory_logs WHERE product_id=:pid'); $c->execute([':pid'=>$pid]); $total=(int)$c->fetchColumn();
            $st=$pdo->prepare('SELECT id,product_id,change_quantity,remaining_quantity,reason,order_id,user_id,note,created_at FROM inventory_logs WHERE product_id=:pid ORDER BY created_at DESC,id DESC LIMIT :l OFFSET :o');
            $st->bindValue(':pid',$pid,PDO::PARAM_INT); $st->bindValue(':l',$limit,PDO::PARAM_INT); $st->bindValue(':o',$off,PDO::PARAM_INT); $st->execute();
            json_response(['success'=>true,'data'=>['product'=>$prod,'total'=>$total,'page'=>$page,'limit'=>$limit,'items'=>$st->fetchAll()]]);
        }
        $page=max(1,(int)($_GET['page']??1)); $limit=max(1,min(100,(int)($_GET['limit']??20))); $off=($page-1)*$limit;
        $low=($_GET['low_stock_only']??'0')==='1'; $w=$low?'WHERE p.stock_quantity <= 5':'';
        $total=(int)$pdo->query("SELECT COUNT(*) FROM products p $w")->fetchColumn();
        $sql="SELECT p.id,p.sku,p.name,p.thumbnail,p.stock_quantity,p.price,p.cost_price,c.name AS categoryName,CASE WHEN p.stock_quantity<=5 THEN 1 ELSE 0 END AS low_stock
              FROM products p LEFT JOIN categories c ON c.id=p.category_id $w ORDER BY p.stock_quantity ASC,p.id ASC LIMIT :l OFFSET :o";
        $st=$pdo->prepare($sql); $st->bindValue(':l',$limit,PDO::PARAM_INT); $st->bindValue(':o',$off,PDO::PARAM_INT); $st->execute();
        json_response(['success'=>true,'data'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'items'=>$st->fetchAll()]]);
    }
    if($method==='POST'){
        $in=get_json_input(); $pid=(int)($in['product_id']??0); $qty=(int)($in['quantity']??0); $reason=trim((string)($in['reason']??'')); $note=trim((string)($in['note']??''));
        if($pid<=0||$qty===0||!in_array($reason,['import','damage','audit_adjustment'],true)) json_response(['success'=>false,'message'=>'Dữ liệu điều chỉnh không hợp lệ.'],400);
        $pdo->beginTransaction();
        $p=$pdo->prepare('SELECT id,stock_quantity FROM products WHERE id=:id FOR UPDATE'); $p->execute([':id'=>$pid]); $prod=$p->fetch();
        if(!$prod){ $pdo->rollBack(); json_response(['success'=>false,'message'=>'Sản phẩm không tồn tại.'],404); }
        $new=(int)$prod['stock_quantity']+$qty; if($new<0){ $pdo->rollBack(); json_response(['success'=>false,'message'=>'Tồn kho sau điều chỉnh không thể âm.'],400); }
        $pdo->prepare('UPDATE products SET stock_quantity=:q WHERE id=:id')->execute([':q'=>$new,':id'=>$pid]);
        $pdo->prepare("INSERT INTO inventory_logs (product_id,change_quantity,remaining_quantity,reason,order_id,user_id,note,created_at) VALUES (:pid,:chg,:rem,:rs,NULL,:uid,:note,NOW())")
            ->execute([':pid'=>$pid,':chg'=>$qty,':rem'=>$new,':rs'=>$reason,':uid'=>($_SESSION['user_id']??null),':note'=>($note!==''?$note:null)]);
        $pdo->commit();
        json_response(['success'=>true,'message'=>'Điều chỉnh tồn kho thành công.','data'=>['product_id'=>$pid,'change'=>$qty,'new_quantity'=>$new,'reason'=>$reason]]);
    }
    json_response(['success'=>false,'message'=>'Phương thức HTTP không được hỗ trợ.'],405);
} catch (Throwable $e){ if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction()) $pdo->rollBack(); error_log('[Admin Inventory] '.$e->getMessage()); json_response(['success'=>false,'message'=>'Lỗi hệ thống khi xử lý tồn kho.'],500);} 
<?php require_once('header.php'); ?>

<?php
$statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
foreach ($result as $row) {
    $banner_product_category = $row['banner_product_category'];
}
?>

<?php
// Handle Add to Cart from Variant Selection Modal
if(isset($_POST['form_add_to_cart'])) {
    $p_id = intval($_POST['p_id']);
    $p_qty = intval($_POST['p_qty']);
    $p_name = isset($_POST['p_name']) ? $_POST['p_name'] : '';
    $p_current_price = isset($_POST['p_current_price']) ? $_POST['p_current_price'] : '0.00';
    $p_featured_photo = isset($_POST['p_featured_photo']) ? $_POST['p_featured_photo'] : '';
    $size_id = isset($_POST['size_id']) ? intval($_POST['size_id']) : 0;
    $size_name = isset($_POST['size_name']) ? $_POST['size_name'] : '';
    $color_id = isset($_POST['color_id']) ? intval($_POST['color_id']) : 0;
    $color_name = isset($_POST['color_name']) ? $_POST['color_name'] : '';

    // Verify stock
    $stmt_chk = $pdo->prepare("SELECT p_qty, p_name, p_current_price, p_featured_photo FROM tbl_product WHERE p_id=?");
    $stmt_chk->execute(array($p_id));
    $prod_chk = $stmt_chk->fetch(PDO::FETCH_ASSOC);
    
    if(!$prod_chk) {
        $error_message1 = 'Selected product could not be found.';
    } else {
        $current_stock = intval($prod_chk['p_qty']);
        $p_current_price = $prod_chk['p_current_price'];
        $p_name = $prod_chk['p_name'];
        $p_featured_photo = $prod_chk['p_featured_photo'];

        if($p_qty <= 0) {
            $p_qty = 1;
        }

        if($p_qty > $current_stock) {
            $error_message1 = 'Sorry! There are only ' . $current_stock . ' item(s) in stock for ' . htmlspecialchars($p_name) . '.';
        } else {
            if(isset($_SESSION['cart_p_id'])) {
                $arr_cart_p_id = array();
                $arr_cart_size_id = array();
                $arr_cart_size_name = array();
                $arr_cart_color_id = array();
                $arr_cart_color_name = array();
                $arr_cart_p_qty = array();
                $arr_cart_p_current_price = array();
                $arr_cart_p_name = array();
                $arr_cart_p_featured_photo = array();

                $i = 0;
                foreach($_SESSION['cart_p_id'] as $key => $value) {
                    $i++;
                    $arr_cart_p_id[$i] = $value;
                    $arr_cart_size_id[$i] = isset($_SESSION['cart_size_id'][$key]) ? $_SESSION['cart_size_id'][$key] : 0;
                    $arr_cart_size_name[$i] = isset($_SESSION['cart_size_name'][$key]) ? $_SESSION['cart_size_name'][$key] : '';
                    $arr_cart_color_id[$i] = isset($_SESSION['cart_color_id'][$key]) ? $_SESSION['cart_color_id'][$key] : 0;
                    $arr_cart_color_name[$i] = isset($_SESSION['cart_color_name'][$key]) ? $_SESSION['cart_color_name'][$key] : '';
                    $arr_cart_p_qty[$i] = isset($_SESSION['cart_p_qty'][$key]) ? $_SESSION['cart_p_qty'][$key] : 1;
                    $arr_cart_p_current_price[$i] = isset($_SESSION['cart_p_current_price'][$key]) ? $_SESSION['cart_p_current_price'][$key] : 0;
                    $arr_cart_p_name[$i] = isset($_SESSION['cart_p_name'][$key]) ? $_SESSION['cart_p_name'][$key] : '';
                    $arr_cart_p_featured_photo[$i] = isset($_SESSION['cart_p_featured_photo'][$key]) ? $_SESSION['cart_p_featured_photo'][$key] : '';
                }

                $added = 0;
                $matched_key = 0;
                for($i = 1; $i <= count($arr_cart_p_id); $i++) {
                    if($arr_cart_p_id[$i] == $p_id && $arr_cart_size_id[$i] == $size_id && $arr_cart_color_id[$i] == $color_id) {
                        $added = 1;
                        $matched_key = $i;
                        break;
                    }
                }

                if($added == 1) {
                    $new_qty = $arr_cart_p_qty[$matched_key] + $p_qty;
                    if($new_qty > $current_stock) {
                        $new_qty = $current_stock;
                        $error_message1 = 'Product is already in cart. Quantity updated to maximum available stock (' . $current_stock . ').';
                    } else {
                        $success_message1 = 'Cart updated successfully!';
                    }
                    $_SESSION['cart_p_qty'][$matched_key] = $new_qty;
                } else {
                    $new_key = count($arr_cart_p_id) + 1;
                    $_SESSION['cart_p_id'][$new_key] = $p_id;
                    $_SESSION['cart_size_id'][$new_key] = $size_id;
                    $_SESSION['cart_size_name'][$new_key] = $size_name;
                    $_SESSION['cart_color_id'][$new_key] = $color_id;
                    $_SESSION['cart_color_name'][$new_key] = $color_name;
                    $_SESSION['cart_p_qty'][$new_key] = $p_qty;
                    $_SESSION['cart_p_current_price'][$new_key] = $p_current_price;
                    $_SESSION['cart_p_name'][$new_key] = $p_name;
                    $_SESSION['cart_p_featured_photo'][$new_key] = $p_featured_photo;
                    $success_message1 = 'Product is added to the cart successfully!';
                }
            } else {
                $_SESSION['cart_p_id'][1] = $p_id;
                $_SESSION['cart_size_id'][1] = $size_id;
                $_SESSION['cart_size_name'][1] = $size_name;
                $_SESSION['cart_color_id'][1] = $color_id;
                $_SESSION['cart_color_name'][1] = $color_name;
                $_SESSION['cart_p_qty'][1] = $p_qty;
                $_SESSION['cart_p_current_price'][1] = $p_current_price;
                $_SESSION['cart_p_name'][1] = $p_name;
                $_SESSION['cart_p_featured_photo'][1] = $p_featured_photo;
                $success_message1 = 'Product is added to the cart successfully!';
            }
        }
    }
}
?>

<?php
if( !isset($_REQUEST['id']) || !isset($_REQUEST['type']) ) {
    header('location: index.php');
    exit;
} else {

    if( ($_REQUEST['type'] != 'top-category') && ($_REQUEST['type'] != 'mid-category') && ($_REQUEST['type'] != 'end-category') ) {
        header('location: index.php');
        exit;
    } else {

        $statement = $pdo->prepare("SELECT * FROM tbl_top_category");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
        foreach ($result as $row) {
            $top[] = $row['tcat_id'];
            $top1[] = $row['tcat_name'];
        }

        $statement = $pdo->prepare("SELECT * FROM tbl_mid_category");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
        foreach ($result as $row) {
            $mid[] = $row['mcat_id'];
            $mid1[] = $row['mcat_name'];
            $mid2[] = $row['tcat_id'];
        }

        $statement = $pdo->prepare("SELECT * FROM tbl_end_category");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
        foreach ($result as $row) {
            $end[] = $row['ecat_id'];
            $end1[] = $row['ecat_name'];
            $end2[] = $row['mcat_id'];
        }

        if($_REQUEST['type'] == 'top-category') {
            if(!in_array($_REQUEST['id'],$top)) {
                header('location: index.php');
                exit;
            } else {
                // Getting Title
                for ($i=0; $i < count($top); $i++) { 
                    if($top[$i] == $_REQUEST['id']) {
                        $title = $top1[$i];
                        break;
                    }
                }
                $arr1 = array();
                $arr2 = array();
                // Find out all ecat ids under this
                for ($i=0; $i < count($mid); $i++) { 
                    if($mid2[$i] == $_REQUEST['id']) {
                        $arr1[] = $mid[$i];
                    }
                }
                for ($j=0; $j < count($arr1); $j++) {
                    for ($i=0; $i < count($end); $i++) { 
                        if($end2[$i] == $arr1[$j]) {
                            $arr2[] = $end[$i];
                        }
                    }   
                }
                $final_ecat_ids = $arr2;
            }   
        }

        if($_REQUEST['type'] == 'mid-category') {
            if(!in_array($_REQUEST['id'],$mid)) {
                header('location: index.php');
                exit;
            } else {
                // Getting Title
                for ($i=0; $i < count($mid); $i++) { 
                    if($mid[$i] == $_REQUEST['id']) {
                        $title = $mid1[$i];
                        break;
                    }
                }
                $arr2 = array();        
                // Find out all ecat ids under this
                for ($i=0; $i < count($end); $i++) { 
                    if($end2[$i] == $_REQUEST['id']) {
                        $arr2[] = $end[$i];
                    }
                }
                $final_ecat_ids = $arr2;
            }
        }

        if($_REQUEST['type'] == 'end-category') {
            if(!in_array($_REQUEST['id'],$end)) {
                header('location: index.php');
                exit;
            } else {
                // Getting Title
                for ($i=0; $i < count($end); $i++) { 
                    if($end[$i] == $_REQUEST['id']) {
                        $title = $end1[$i];
                        break;
                    }
                }
                $final_ecat_ids = array($_REQUEST['id']);
            }
        }
        
    }   
}

// Fetch all active products in the category scope and group by End Level Category & Parent Product
$grouped_category_products = array();

if (!empty($final_ecat_ids)) {
    $in_clause = implode(',', array_fill(0, count($final_ecat_ids), '?'));
    $statement = $pdo->prepare("SELECT p.*, ec.ecat_name, s.supplier_name 
        FROM tbl_product p 
        LEFT JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id
        LEFT JOIN tbl_supplier s ON p.supplier_id = s.supplier_id
        WHERE p.ecat_id IN ($in_clause) AND p.p_is_active = 1
        ORDER BY ec.ecat_name ASC, p.p_name ASC");
    $statement->execute($final_ecat_ids);
    $raw_category_products = $statement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw_category_products as $prod) {
        $clean_price = floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_current_price'])));
        $clean_old_price = !empty($prod['p_old_price']) ? floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_old_price']))) : 0;
        $clean_stock = intval(preg_replace('/[^0-9]/', '', strval($prod['p_qty'])));
        $img_src = (!empty($prod['p_featured_photo']) && file_exists('assets/uploads/'.$prod['p_featured_photo'])) 
            ? $prod['p_featured_photo'] 
            : 'photo-6.jpg';

        $parsed_spec = parseConstructionProductDetails($prod['p_name']);
        $base_name = $parsed_spec['base_name'];

        // Grouping key: ecat_id + sanitized base_name
        $group_key = $prod['ecat_id'] . '_' . strtolower(preg_replace('/[^a-z0-9]/', '', $base_name));

        // Get ratings for this variant
        $statement_r = $pdo->prepare("SELECT rating FROM tbl_rating WHERE p_id=?");
        $statement_r->execute(array($prod['p_id']));
        $ratings = $statement_r->fetchAll(PDO::FETCH_COLUMN);
        $rating_count = count($ratings);
        $avg_rating = $rating_count > 0 ? (array_sum($ratings) / $rating_count) : 0;

        $variant_item = array(
            'id' => intval($prod['p_id']),
            'sku' => !empty($prod['p_sku']) ? $prod['p_sku'] : ('SKU-' . str_pad($prod['p_id'], 5, '0', STR_PAD_LEFT)),
            'name' => $prod['p_name'],
            'base_name' => $base_name,
            'spec_label' => $parsed_spec['spec_label'],
            'size' => $parsed_spec['size'],
            'thickness' => $parsed_spec['thickness'],
            'diameter' => $parsed_spec['diameter'],
            'color' => $parsed_spec['color'],
            'material' => $parsed_spec['material'],
            'weight_pack' => $parsed_spec['weight_pack'],
            'voltage' => $parsed_spec['voltage'],
            'power' => $parsed_spec['power'],
            'rated_current' => $parsed_spec['rated_current'],
            'length' => $parsed_spec['length'],
            'price' => $clean_price,
            'old_price' => $clean_old_price,
            'stock' => $clean_stock,
            'photo' => $img_src,
            'brand' => !empty($prod['p_brand']) ? $prod['p_brand'] : 'Generic',
            'rating' => $avg_rating,
            'rating_count' => $rating_count,
            'supplier_name' => !empty($prod['supplier_name']) ? $prod['supplier_name'] : ''
        );

        if (!isset($grouped_category_products[$group_key])) {
            $grouped_category_products[$group_key] = array(
                'group_key' => $group_key,
                'base_name' => $base_name,
                'ecat_id' => $prod['ecat_id'],
                'ecat_name' => !empty($prod['ecat_name']) ? $prod['ecat_name'] : 'General',
                'brand' => !empty($prod['p_brand']) ? $prod['p_brand'] : 'Generic',
                'photo' => $img_src,
                'min_price' => $clean_price,
                'max_price' => $clean_price,
                'total_stock' => $clean_stock,
                'primary_id' => intval($prod['p_id']),
                'supplier_name' => !empty($prod['supplier_name']) ? $prod['supplier_name'] : '',
                'avg_rating' => $avg_rating,
                'variants' => array($variant_item)
            );
        } else {
            $grouped_category_products[$group_key]['variants'][] = $variant_item;
            $grouped_category_products[$group_key]['total_stock'] += $clean_stock;
            if ($clean_price < $grouped_category_products[$group_key]['min_price']) {
                $grouped_category_products[$group_key]['min_price'] = $clean_price;
            }
            if ($clean_price > $grouped_category_products[$group_key]['max_price']) {
                $grouped_category_products[$group_key]['max_price'] = $clean_price;
            }
        }
    }
}
?>

<style>
.cat-variant-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    border: 1.5px solid #cbd5e1;
    background: #fff;
    color: #1e293b;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    text-decoration: none !important;
}
.cat-variant-chip:hover {
    border-color: #e41414;
    background: #fef2f2;
    color: #e41414;
}
.cat-variant-chip.active {
    border-color: #e41414;
    background: #e41414;
    color: #fff;
}
.cat-variant-chip.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f1f5f9;
    border-color: #e2e8f0;
}
.cat-color-badge {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 800;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    text-transform: uppercase;
}
.cat-color-orange { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
.cat-color-green { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.cat-color-blue { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.cat-color-yellow { background: #fefce8; color: #a16207; border-color: #fef08a; }
.cat-color-red { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.cat-color-black { background: #1e293b; color: #f8fafc; border-color: #0f172a; }
.cat-color-brown { background: #fdf8f6; color: #7c2d12; border-color: #fed7aa; }
.cat-color-stainless { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
.cat-color-galvanized { background: #f0fdfa; color: #0f766e; border-color: #99f6e4; }
.item-product-cat .inner {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    margin-bottom: 25px;
    transition: all 0.25s ease;
    background: #fff;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.item-product-cat .inner:hover {
    box-shadow: 0 8px 20px rgba(0,0,0,0.12) !important;
    transform: translateY(-3px);
    border-color: #e41414;
}
</style>

<?php
if($error_message1 != '') {
    echo "<script>alert('".addslashes($error_message1)."');</script>";
}
if($success_message1 != '') {
    echo "<script>alert('".addslashes($success_message1)."');</script>";
}
?>

<div class="page-banner" style="background-image: url(assets/uploads/<?php echo $banner_product_category; ?>)">
    <div class="inner">
        <h1><?php echo LANG_VALUE_50; ?> <?php echo $title; ?></h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-3">
                <?php require_once('sidebar-category.php'); ?>
            </div>
            <div class="col-md-9">
                
                <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
                    <h3 style="margin: 0; font-weight: bold; color: #0f172a;"><?php echo LANG_VALUE_51; ?> "<?php echo $title; ?>"</h3>
                    <span class="text-muted" style="font-size: 14px;">
                        Showing <strong><?php echo count($grouped_category_products); ?></strong> Products Available
                    </span>
                </div>

                <div class="product product-cat">
                    <div class="row">
                        <?php if (count($grouped_category_products) == 0): ?>
                            <div class="col-md-12 text-center" style="padding: 50px 20px;">
                                <i class="fa fa-cubes fa-3x" style="color: #cbd5e1;"></i>
                                <p class="text-muted" style="margin-top: 15px; font-size: 15px;"><?php echo LANG_VALUE_153; ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($grouped_category_products as $group_key => $group): 
                                $is_out_of_stock = ($group['total_stock'] <= 0);
                                $variant_count = count($group['variants']);
                            ?>
                            <div class="col-md-4 item item-product-cat">
                                <div class="inner">
                                    <div class="thumb" style="position: relative;">
                                        <div class="photo" style="background-image:url(assets/uploads/<?php echo $group['photo']; ?>); height: 210px; background-size: contain; background-repeat: no-repeat; background-position: center; background-color: #fff;"></div>
                                        <div class="overlay"></div>
                                        
                                        <!-- End Category Badge -->
                                        <span style="position: absolute; top: 10px; left: 10px; background: rgba(15, 23, 42, 0.85); color: #fff; font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.3px; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($group['ecat_name']); ?>
                                        </span>

                                        <!-- Stock Badge -->
                                        <?php if ($is_out_of_stock): ?>
                                            <span class="label label-danger" style="position: absolute; top: 10px; right: 10px; font-size: 11px; padding: 4px 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">Out Of Stock</span>
                                        <?php else: ?>
                                            <span class="label label-success" style="position: absolute; top: 10px; right: 10px; font-size: 11px; padding: 4px 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"><?php echo $group['total_stock']; ?> in stock</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="text" style="padding: 15px; text-align: center;">
                                        <h3 style="margin-top: 0; min-height: 42px; font-size: 16px; font-weight: bold; line-height: 1.3;">
                                            <a href="javascript:void(0)" onclick="handleCategoryProductClick(this)" data-group='<?php echo htmlspecialchars(json_encode($group), ENT_QUOTES, 'UTF-8'); ?>' style="color: #0f172a; text-decoration: none;">
                                                <?php echo htmlspecialchars($group['base_name']); ?>
                                            </a>
                                        </h3>
                                        
                                        <!-- Variant Indicator Badge -->
                                        <div style="margin-bottom: 8px;">
                                            <?php if ($variant_count > 1): ?>
                                                <span style="display: inline-block; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 4px;">
                                                    <i class="fa fa-th-list"></i> <?php echo $variant_count; ?> Variants / Sizes
                                                </span>
                                            <?php else: ?>
                                                <span style="display: inline-block; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 4px;">
                                                    <i class="fa fa-cube"></i> <?php echo htmlspecialchars($group['variants'][0]['spec_label']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Price Range or Single Price -->
                                        <h4 style="color: #e41414; font-weight: 900; font-size: 17px; margin: 8px 0;">
                                            <?php if ($group['min_price'] == $group['max_price']): ?>
                                                <?php echo LANG_VALUE_1; ?><?php echo number_format($group['min_price'], 2); ?>
                                            <?php else: ?>
                                                <?php echo LANG_VALUE_1; ?><?php echo number_format($group['min_price'], 2); ?> - <?php echo LANG_VALUE_1; ?><?php echo number_format($group['max_price'], 2); ?>
                                            <?php endif; ?>
                                        </h4>

                                        <!-- Rating Stars -->
                                        <div class="rating" style="margin-bottom: 12px; min-height: 20px;">
                                            <?php
                                            $avg_rating = $group['avg_rating'];
                                            if($avg_rating == 0) {
                                                echo '<span style="color:#94a3b8; font-size:12px;"><i class="fa fa-star-o"></i> No reviews</span>';
                                            } else {
                                                for($i=1;$i<=5;$i++) {
                                                    if($i <= floor($avg_rating)) {
                                                        echo '<i class="fa fa-star" style="color:#f59e0b;"></i> ';
                                                    } elseif($i - 0.5 <= $avg_rating) {
                                                        echo '<i class="fa fa-star-half-o" style="color:#f59e0b;"></i> ';
                                                    } else {
                                                        echo '<i class="fa fa-star-o" style="color:#cbd5e1;"></i> ';
                                                    }
                                                }
                                            }
                                            ?>
                                        </div>

                                        <!-- Add Button -->
                                        <?php if ($is_out_of_stock): ?>
                                            <div class="out-of-stock">
                                                <div class="inner">Out Of Stock</div>
                                            </div>
                                        <?php else: ?>
                                            <p style="margin-bottom: 0;">
                                                <a href="javascript:void(0)" class="btn btn-primary btn-sm btn-block" style="background: #e41414; border-color: #e41414; font-weight: bold; padding: 8px 12px; border-radius: 4px; color: #fff; text-decoration: none;" onclick="handleCategoryProductClick(this)" data-group='<?php echo htmlspecialchars(json_encode($group), ENT_QUOTES, 'UTF-8'); ?>'>
                                                    <i class="fa fa-shopping-cart"></i> <?php echo ($variant_count > 1) ? '+ Add / Select Variant' : '+ Add to Cart'; ?>
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Interactive Variant Selection Modal for Category Page -->
<div class="modal fade" id="categoryVariantModal" tabindex="-1" role="dialog" aria-labelledby="catVariantModalLabel" aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog" role="document" style="max-width: 540px; margin-top: 60px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: none;">
            
            <div class="modal-header" style="background: #1e3a8a; color: #fff; padding: 14px 18px; border-bottom: none;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.9; font-size: 24px; text-shadow: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="catVariantModalLabel" style="font-weight: 700; display: flex; align-items: center; gap: 8px; font-size: 18px; margin: 0; color: #fff;">
                    <i class="fa fa-cubes text-info"></i> <span id="catModalTitle">Select Variant</span>
                </h4>
                <div style="margin-top: 4px; font-size: 12px; color: #bfdbfe;">
                    Category: <strong id="catModalCategory" style="color: #fff;">-</strong>
                    <span id="catModalBrandWrap" style="margin-left: 10px;">| Brand: <strong id="catModalBrand" style="color: #fff;">-</strong></span>
                </div>
            </div>
            
            <form method="POST" action="" id="catAddToCartForm">
                <?php $csrf->echoInputField(); ?>
                <input type="hidden" name="form_add_to_cart" value="1">
                <input type="hidden" name="p_id" id="catHiddenPId" value="">
                <input type="hidden" name="p_name" id="catHiddenPName" value="">
                <input type="hidden" name="p_current_price" id="catHiddenPrice" value="">
                <input type="hidden" name="p_featured_photo" id="catHiddenPhoto" value="">
                <input type="hidden" name="size_id" id="catHiddenSizeId" value="0">
                <input type="hidden" name="size_name" id="catHiddenSizeName" value="">
                <input type="hidden" name="color_id" id="catHiddenColorId" value="0">
                <input type="hidden" name="color_name" id="catHiddenColorName" value="">

                <div class="modal-body" style="padding: 20px;">
                    
                    <!-- Selected Variant Live Details Card -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 16px; display: flex; gap: 14px; align-items: center;">
                        <div id="catModalImg" style="width: 85px; height: 85px; min-width: 85px; border-radius: 6px; background-size: contain; background-repeat: no-repeat; background-position: center; background-color: #fff; border: 1px solid #cbd5e1;"></div>
                        <div style="flex-grow: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                <h4 id="catModalSelectedName" style="margin: 0 0 4px 0; font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.3;">-</h4>
                                <span id="catModalStockBadge" class="label label-success" style="font-size: 11px; padding: 4px 8px; white-space: nowrap;">In Stock</span>
                            </div>
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 6px;">
                                SKU: <strong id="catModalSku" style="color: #334155; font-family: monospace; font-size: 13px;">-</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 6px;">
                                <div style="font-size: 20px; font-weight: 900; color: #e41414;">
                                    &#8369;<span id="catModalPrice">0.00</span>
                                </div>
                                <div id="catModalSpecTags" style="display: flex; flex-wrap: wrap; gap: 4px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Variant Dropdown Selection -->
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">
                            <i class="fa fa-list-ul text-primary"></i> Select Specification / Variant:
                        </label>
                        <select id="catModalSelect" class="form-control input-lg" style="height: 42px; font-size: 14px; font-weight: 600;" onchange="onCategoryVariantSelectChange(this.value)">
                        </select>
                    </div>

                    <!-- Clickable Variant Chips -->
                    <div id="catModalChipsContainer" style="margin-bottom: 16px;">
                        <label style="font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 6px; display: block; text-transform: uppercase;">
                            Quick Variant Selector:
                        </label>
                        <div id="catModalChipsList" style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 120px; overflow-y: auto; padding: 2px;"></div>
                    </div>

                    <!-- Quantity & Live Subtotal -->
                    <div style="background: #f1f5f9; border-radius: 8px; padding: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">Quantity:</label>
                            <div class="input-group" style="width: 140px;">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" style="font-weight: bold; font-size: 16px; padding: 6px 12px;" onclick="changeCatModalQty(-1)">-</button>
                                </span>
                                <input type="number" name="p_qty" id="catModalQtyInput" class="form-control text-center" style="font-size: 16px; font-weight: 800; height: 38px;" value="1" min="1" oninput="onCatModalQtyChange()">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" style="font-weight: bold; font-size: 16px; padding: 6px 12px;" onclick="changeCatModalQty(1)">+</button>
                                </span>
                            </div>
                        </div>
                        
                        <div style="text-align: right;">
                            <span style="font-size: 12px; color: #64748b; display: block; font-weight: 600;">Total Amount:</span>
                            <span style="font-size: 22px; font-weight: 900; color: #047857;">&#8369;<span id="catModalItemSubtotal">0.00</span></span>
                        </div>
                    </div>

                </div>

                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <a id="catModalViewDetailsLink" href="#" class="btn btn-default" style="font-weight: 600;">
                        <i class="fa fa-info-circle"></i> View Details
                    </a>
                    <div>
                        <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; margin-right: 6px;">Close</button>
                        <button type="submit" id="catModalAddBtn" class="btn btn-primary" style="font-weight: 800; padding: 8px 22px; font-size: 15px; background: #e41414; border-color: #e41414;">
                            <i class="fa fa-shopping-cart"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentCategoryGroup = null;
let currentSelectedCatVariant = null;

function handleCategoryProductClick(element) {
    const rawData = element.getAttribute('data-group');
    if (!rawData) return;
    try {
        const group = JSON.parse(rawData);
        if (!group || !group.variants || group.variants.length === 0) return;
        if (group.total_stock <= 0) {
            alert('This product is currently out of stock.');
            return;
        }
        openCategoryVariantModal(group);
    } catch (e) {
        console.error('Failed to parse group data', e);
    }
}

function openCategoryVariantModal(group) {
    currentCategoryGroup = group;
    
    document.getElementById('catModalTitle').innerText = group.base_name;
    document.getElementById('catModalCategory').innerText = group.ecat_name || 'General';
    document.getElementById('catModalBrand').innerText = group.brand || 'Generic';
    
    const select = document.getElementById('catModalSelect');
    const chipsList = document.getElementById('catModalChipsList');
    
    select.innerHTML = '';
    chipsList.innerHTML = '';
    
    let firstInStock = null;
    
    group.variants.forEach((v) => {
        const isOutOfStock = (v.stock <= 0);
        if (!firstInStock && !isOutOfStock) {
            firstInStock = v;
        }
        
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.disabled = isOutOfStock;
        opt.innerText = `${v.spec_label} - ₱${v.price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} (${isOutOfStock ? 'Out of stock' : v.stock + ' in stock'})`;
        select.appendChild(opt);
        
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = `cat-variant-chip ${isOutOfStock ? 'disabled' : ''}`;
        chip.id = `catChip_${v.id}`;
        chip.title = isOutOfStock ? 'Out of stock' : `${v.stock} in stock`;
        chip.innerHTML = `<i class="fa ${isOutOfStock ? 'fa-ban text-danger' : 'fa-check-circle'}"></i> ${escapeHtml(v.spec_label)} <span style="font-weight: 800; margin-left: 2px;">₱${v.price.toFixed(0)}</span>`;
        if (!isOutOfStock) {
            chip.onclick = function() { onCategoryVariantSelectChange(v.id); };
        }
        chipsList.appendChild(chip);
    });
    
    const selectedVariant = firstInStock || group.variants[0];
    document.getElementById('catModalQtyInput').value = 1;
    onCategoryVariantSelectChange(selectedVariant.id);
    
    $('#categoryVariantModal').modal('show');
}

function onCategoryVariantSelectChange(variantId) {
    variantId = parseInt(variantId);
    if (!currentCategoryGroup || !currentCategoryGroup.variants) return;
    
    const variant = currentCategoryGroup.variants.find(v => v.id === variantId);
    if (!variant) return;
    
    currentSelectedCatVariant = variant;
    
    // Sync Select Dropdown
    document.getElementById('catModalSelect').value = variant.id;
    
    // Sync Active Chip
    document.querySelectorAll('.cat-variant-chip').forEach(c => c.classList.remove('active'));
    const activeChip = document.getElementById(`catChip_${variant.id}`);
    if (activeChip) activeChip.classList.add('active');
    
    // Update Hidden Form Inputs
    document.getElementById('catHiddenPId').value = variant.id;
    document.getElementById('catHiddenPName').value = variant.name;
    document.getElementById('catHiddenPrice').value = variant.price;
    document.getElementById('catHiddenPhoto').value = variant.photo;
    document.getElementById('catHiddenSizeName').value = variant.size || variant.spec_label || '';
    document.getElementById('catHiddenColorName').value = variant.color || '';
    
    // Update View Details link
    document.getElementById('catModalViewDetailsLink').href = `product.php?id=${variant.id}`;
    
    // Update Preview
    document.getElementById('catModalImg').style.backgroundImage = `url('assets/uploads/${variant.photo}')`;
    document.getElementById('catModalSelectedName').innerText = variant.name;
    document.getElementById('catModalSku').innerText = variant.sku;
    document.getElementById('catModalPrice').innerText = variant.price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Update Stock Badge & Button State
    const stockBadge = document.getElementById('catModalStockBadge');
    const addBtn = document.getElementById('catModalAddBtn');
    const qtyInput = document.getElementById('catModalQtyInput');
    
    if (variant.stock <= 0) {
        stockBadge.className = 'label label-danger';
        stockBadge.innerText = 'Out of Stock';
        addBtn.disabled = true;
        qtyInput.disabled = true;
    } else {
        stockBadge.className = (variant.stock < 10) ? 'label label-warning' : 'label label-success';
        stockBadge.innerText = `${variant.stock} in stock`;
        addBtn.disabled = false;
        qtyInput.disabled = false;
        qtyInput.max = variant.stock;
        
        let currentQty = parseInt(qtyInput.value) || 1;
        if (currentQty > variant.stock) {
            qtyInput.value = variant.stock;
        } else if (currentQty < 1) {
            qtyInput.value = 1;
        }
    }
    
    // Render Specification Tags
    const specTagsContainer = document.getElementById('catModalSpecTags');
    let tagsHtml = [];
    if (variant.size) tagsHtml.push(`<span style="color: #0369a1; font-weight: 700; background: #e0f2fe; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Size: ${escapeHtml(variant.size)}</span>`);
    if (variant.thickness) tagsHtml.push(`<span style="color: #047857; font-weight: 700; background: #dcfce7; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Thick: ${escapeHtml(variant.thickness)}</span>`);
    if (variant.diameter) tagsHtml.push(`<span style="color: #047857; font-weight: 700; background: #dcfce7; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Dia: ${escapeHtml(variant.diameter)}</span>`);
    if (variant.color) tagsHtml.push(`<span class="cat-color-badge cat-color-${variant.color.toLowerCase()}">${escapeHtml(variant.color)}</span>`);
    if (variant.material) tagsHtml.push(`<span style="color: #475569; font-weight: 700; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 11px;">${escapeHtml(variant.material)}</span>`);
    if (variant.voltage) tagsHtml.push(`<span style="color: #b45309; font-weight: 700; background: #fef3c7; padding: 2px 6px; border-radius: 4px; font-size: 11px;">${escapeHtml(variant.voltage)}</span>`);
    if (variant.length) tagsHtml.push(`<span style="color: #4338ca; font-weight: 700; background: #e0e7ff; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Len: ${escapeHtml(variant.length)}</span>`);
    specTagsContainer.innerHTML = tagsHtml.join(' ');
    
    calcCatModalSubtotal();
}

function changeCatModalQty(delta) {
    if (!currentSelectedCatVariant) return;
    const qtyInput = document.getElementById('catModalQtyInput');
    let currentVal = parseInt(qtyInput.value) || 1;
    let newVal = currentVal + delta;
    if (newVal < 1) newVal = 1;
    if (newVal > currentSelectedCatVariant.stock) {
        alert(`Cannot exceed available inventory (${currentSelectedCatVariant.stock} units).`);
        newVal = currentSelectedCatVariant.stock;
    }
    qtyInput.value = newVal;
    calcCatModalSubtotal();
}

function onCatModalQtyChange() {
    if (!currentSelectedCatVariant) return;
    const qtyInput = document.getElementById('catModalQtyInput');
    let val = parseInt(qtyInput.value) || 1;
    if (val < 1) val = 1;
    if (val > currentSelectedCatVariant.stock) {
        alert(`Maximum available inventory is ${currentSelectedCatVariant.stock} units.`);
        val = currentSelectedCatVariant.stock;
    }
    qtyInput.value = val;
    calcCatModalSubtotal();
}

function calcCatModalSubtotal() {
    if (!currentSelectedCatVariant) return;
    const qty = parseInt(document.getElementById('catModalQtyInput').value) || 1;
    const subtotal = currentSelectedCatVariant.price * qty;
    document.getElementById('catModalItemSubtotal').innerText = subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}
</script>

<?php require_once('footer.php'); ?>
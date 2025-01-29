<?php
global $wpdb;

$page = $_GET['page'] ?? "";
$paged = intval($_GET['paged'] ?? 1);

$posts_per_page = 30;

$args = array(
    'post_type' => 'post',
    'posts_per_page' => $posts_per_page,
    'paged' => $paged,
);

$search = $_REQUEST['search'] ?? '';
$orderby = $_REQUEST['orderby'] ?? 'date';
$order = $_REQUEST['order'] ?? 'DESC';
$category_id = $_REQUEST['category-id'] ?? 0;
$author_id = $_REQUEST['author-id'] ?? 0;
$months = $_REQUEST['months'] ?? 0;
$from = $_REQUEST['from'] ?? "";
$to = $_REQUEST['to'] ?? "";
$months = intval($months);
$checboxes = ['exclude_pinecone_export', 'dcio_pinecone_exported'];

if (in_array($orderby, $checboxes)) {
    $args = array('post_type' => 'post', 'paged' => $paged, 'post_status' => 'publish', 'posts_per_page' => $posts_per_page, 'meta_key' => $orderby, 'orderby' => 'date', 'order'  => $order);
} else {
    $args = array('post_type' => 'post', 'paged' => $paged, 'post_status' => 'publish', 'posts_per_page' => $posts_per_page, 'orderby' => $orderby, 'order'  => $order);
}
if ($category_id) {
    $args['cat'] = $category_id;
}
if ($author_id) {
    $args['author'] = $author_id;
}
if ($search) {
    //ermittelt die post_ids in welchen das Suchwort vorkommt
    $sql = $wpdb->prepare(
        "SELECT ID FROM $wpdb->posts WHERE (post_status = 'publish' OR post_status = 'future' OR post_status = 'draft') AND post_title LIKE %s;",
        '%' . $wpdb->esc_like($search) . '%'
    );
    $postids = $wpdb->get_col($sql);
    if (empty($postids)) {
        $postids = ["0"];
    }
    $args['post__in'] = $postids;
}

// Get the plugin instance to access the handler
$plugin = DCIO_Pinecone_Export::get_instance();
$pinecone_handler = $plugin->get_pinecone_handler();

$date_from = $pinecone_handler->get_day_month_year($from);
$date_to = $pinecone_handler->get_day_month_year($to);

$date_query = [];

if (!empty($date_from) && !empty($date_to)) {
    $date_query[] = ['relation' => 'AND'];
}

if (!empty($date_from)) {
    $date_query[] = [
        'after' => [
            'year' => $date_from["year"],
            'month' => $date_from["month"],
            'day' => $date_from["day"],
        ],
        'inclusive' => true
    ];
}

if (!empty($date_to)) {
    $date_query[] = [
        'before' => [
            'year' => $date_to["year"],
            'month' => $date_to["month"],
            'day' => $date_to["day"],
        ],
        'inclusive' => true
    ];
}

if (!empty($date_query)) {
    $args['date_query'] = $date_query;
}

$wpb_all_query  = new WP_Query($args);
$max_num_pages = $wpb_all_query->max_num_pages;


add_filter('paginate_links', function ($link) {
    return str_replace('/de', '', $link);
});

?>

<div class="paywall-manager">
    <form action="<?php echo get_admin_url() ?>admin.php" method="get">
        <input type="text" id="search" name="search" value="<?= esc_html($search) ?>" placeholder="Search article ...">
        <input type="hidden" id="page" name="page" value="<?= $page ?>">
        <select name="orderby" id="orderby">
            <option value="date" <?= ($orderby === 'date') ? 'selected' : '' ?>>Date</option>
            <option value="title" <?= ($orderby === 'title') ? 'selected' : '' ?>>Article</option>
            <option value="exclude_pinecone_export" <?= ($orderby === 'exclude_pinecone_export') ? 'selected' : '' ?>>Excluded</option>
            <option value="dcio_pinecone_exported" <?= ($orderby === 'dcio_pinecone_exported') ? 'selected' : '' ?>>Exported</option>
        </select>
        <select name="category-id" id="category-id">
            <option value="0">All categories</option>
            <?php foreach (get_categories() as $category) : ?>
                <option value="<?= $category->cat_ID ?>" <?= ($category_id == $category->cat_ID) ? ' selected' : '' ?>><?= $category->name ?></option>
            <?php endforeach; ?>
        </select>
        <select name="author-id" id="author-id">
            <option value="0">All authors</option>
            <?php foreach (get_users(array('role__in' => array('author', 'editor', 'administrator'))) as $author) : ?>
                <?php if (!empty($author->description)) : ?>
                    <option value="<?= $author->ID ?>" <?= ($author_id == $author->ID) ? ' selected' : '' ?>><?= $author->display_name  ?></option>
                <?php endif ?>
            <?php endforeach; ?>
        </select>
        <select name="order" id="order">
            <option value="DESC" <?= ($order === 'DESC') ? 'selected' : '' ?>>Sort descending</option>
            <option value="ASC" <?= ($order === 'ASC') ? 'selected' : '' ?>>Sort ascending</option>
        </select>
        <input type="text" id="from" name="from" placeholder="from (Date)" value="<?= esc_html($from) ?>" autocomplete="off">
        <input type="text" id="to" name="to" placeholder="to (Date)" value="<?= esc_html($to) ?>" autocomplete="off">
        <input class="submit-button" type="submit" value="Filter">
    </form>

    <table>
        <tbody>
            <tr>
                <th>Article</th>
                <th>Date</th>
                <th>ID</th>
                <th>Exlude from Export</th>
                <th>Exported</th>
            </tr>

            <?php if ($wpb_all_query->have_posts()) : ?>
                <?php while ($wpb_all_query->have_posts()) : $wpb_all_query->the_post();
                    $post_id = get_the_ID();
                ?>

                    <tr>
                        <td><a href="<?php the_permalink(); ?>" style="color:black;" target="_blank"><?php the_title(); ?></a>
                            <div class="mixed-keywords">
                                <p class="description" for="keywords-22514">Keywords getrennt durch ein Semikolon z.B. Keyword1;Keyword2</p>
                                <input type="text" id="keywords-22514" size="50" value=""><button class="update-keywords submit-button">Update</button>
                                <input type="hidden" value="22514">
                            </div>
                        </td>
                        <td><?php echo esc_attr(get_the_date()); ?></td>
                        <td><?= $post_id ?></td>
                        <td><input class="exclude_pinecone_export" type="checkbox" <?php echo get_post_meta($post_id, 'exclude_pinecone_export', true) ? 'checked="checked"' : '' ?> value="<?php echo $post_id ?>"></td>
                        <td><input class="dcio_pinecone_exported" type="checkbox" <?php echo get_post_meta($post_id, 'dcio_pinecone_exported', true) ? 'checked="checked"' : '' ?> value="<?php echo $post_id ?>"></td>
                    </tr>

                <?php endwhile; ?>


        </tbody>
    </table>
    <div class="pagination">
        <?php
                echo paginate_links(array(
                    // 'base'         => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                    'total'        => $max_num_pages,
                    'current'      => max(1, $paged),
                    'format'       => '?paged=%#%',
                    'show_all'     => false,
                    'type'         => 'plain',
                    'end_size'     => 1,
                    'mid_size'     => 6,
                    'prev_next'    => true,
                    'add_args' => array(
                        'serach' => $search,
                        'orderby' => $orderby,
                        'order' => $order,
                        'from' => $from,
                        'to' => $to,
                        'category-id' => $category_id,
                    ),
                    'add_fragment' => '',
                ));
        ?>
    </div>
    <?php wp_reset_postdata(); ?>
<?php endif; ?>
</div>


<script>
    jQuery(document).ready(function($) {
        $.datepicker.regional['de'] = {
            clearText: 'löschen',
            clearStatus: 'aktuelles Datum löschen',
            closeText: 'schließen',
            closeStatus: 'ohne Änderungen schließen',
            prevText: '<zurück',
            prevStatus: 'letzten Monat zeigen',
            nextText: 'Vor>',
            nextStatus: 'nächsten Monat zeigen',
            currentText: 'heute',
            currentStatus: '',
            monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
            ],
            monthNamesShort: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun',
                'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'
            ],
            monthStatus: 'anderen Monat anzeigen',
            yearStatus: 'anderes Jahr anzeigen',
            weekHeader: 'Wo',
            weekStatus: 'Woche des Monats',
            dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
            dayNamesShort: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
            dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
            dayStatus: 'Setze DD als ersten Wochentag',
            dateStatus: 'Wähle D, M d',
            dateFormat: 'dd.mm.yy',
            firstDay: 1,
            initStatus: 'Wähle ein Datum',
            isRTL: false
        };
        $.datepicker.setDefaults($.datepicker.regional['de']);
        var dateFormat = "dd.mm.yy",
            from = $("#from")
            .datepicker({
                defaultDate: "+1w",
                changeMonth: true,
                changeYear: true,
                numberOfMonths: 1
            })
            .on("change", function() {
                to.datepicker("option", "minDate", getDate(this));
            }),
            to = $("#to").datepicker({
                defaultDate: "+1w",
                changeMonth: true,
                changeYear: true,
                numberOfMonths: 1
            })
            .on("change", function() {
                from.datepicker("option", "maxDate", getDate(this));
            });

        function getDate(element) {
            var date;
            try {
                date = $.datepicker.parseDate(dateFormat, element.value);
            } catch (error) {
                date = null;
            }

            return date;
        }
    });
</script>
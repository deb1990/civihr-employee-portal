<?php

/**
 * @file
 * Template to display a view as a table.
 *
 * - $title : The title of this group of rows.  May be empty.
 * - $header: An array of header labels keyed by field id.
 * - $caption: The caption for this table. May be empty.
 * - $header_classes: An array of header classes keyed by field id.
 * - $fields: An array of CSS IDs to use for each field id.
 * - $classes: A class or classes to apply to the table, based on settings.
 * - $row_classes: An array of classes to apply to each row, indexed by row
 *   number. This matches the index in $rows.
 * - $rows: An array of row items. Each row is an array of content.
 *   $rows are keyed by row number, fields within rows are keyed by field ID.
 * - $field_classes: An array of classes to apply to each field, indexed by
 *   field id, then row number. This matches the index in $rows.
 * @ingroup views_templates
 */

global $user;
$civiUser = get_civihr_uf_match_data($user->uid);

$filters = array(
    0 => t('All'),
    1 => t('Overdue'),
    2 => t('Due'),
    3 => t('Previous'),
);
$filtersCount = array_combine(array_keys($filters), array_fill(0, count($filters), 0));

// Calculating filter counters.
// TODO: maybe move this code part below into separate function in SSP module?
foreach ($rows as $row):
    $filtersCount[_get_appraisal_manager_filter_type($row['status_id'], $row['self_appraisal_due'], $row['manager_appraisal_due'], $row['grade_due'])]++;
endforeach;

function _get_appraisal_manager_filter_type($status, $selfAppraisalDue, $managerAppraisalDue, $gradeDue) {
    $today = date('Y-m-d');
    if ($status === 5) {
        return 3;
    }
    if ($status === 1 && $selfAppraisalDue < $today) {
        return 1;
    }
    if ($status === 2 && $managerAppraisalDue < $today) {
        return 1;
    }
    if ($status === 3 && $gradeDue < $today) {
        return 1;
    }
    return 2;
}

?>

<div class="chr_table-w-filters row">
    <div class="chr_table-w-filters__filters col-md-3">
        <div class="chr_table-w-filters__filters__dropdown-wrapper">
            <div class="chr_custom-select chr_custom-select--full">
                <select id="select-appraisals-filter" class="chr_table-w-filters__filters__dropdown skip-js-custom-select">
                    <?php foreach ($filters as $key => $value): ?>
                        <option value="<?php print $key; ?>"><?php print $value; ?> (<?php print $filtersCount[$key]; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <ul id="nav-appraisals-filter" class="chr_table-w-filters__filters__nav">
            <?php $classActive = ' class="active"'; ?>
            <?php foreach ($filters as $key => $value): ?>
                <?php $badgeType = $key == 1 ? 'danger' : 'primary'; ?>
                <li<?php print $classActive; ?>>
                    <a href data-appraisal-filter="<?php print $key; ?>">
                        <?php print $value; ?>
                        <span class="badge badge-<?php print $badgeType; ?> pull-right appraisal-counter-filter-<?php print $key; ?>">
                            <?php print $filtersCount[$key]; ?>
                        </span>
                    </a>
                </li>
                <?php $classActive = ''; ?>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="chr_table-w-filters__table-wrapper col-md-9">
        <div class="chr_table-w-filters__table">
            <table id="appraisals-manager-table" <?php if ($classes) { print 'class="'. $classes . ' appraisals-manager-table" '; } ?><?php print $attributes; ?>>
                <?php if (!empty($title) || !empty($caption)) : ?>
                    <caption><?php print $caption . $title; ?></caption>
                <?php endif; ?>
                <?php if (!empty($header)) : ?>
                    <thead>
                        <tr>
                        <?php foreach ($header as $field => $label): ?>
                            <th <?php if ($header_classes[$field]) { print 'class="'. $header_classes[$field] . '" '; } ?>>
                                <?php print $label; ?>
                            </th>
                        <?php endforeach; ?>
                            <th></th>
                            <th class="appraisal-column-view-manager-appraisal"></th>
                            <th class="appraisal-column-upload-appraisal"></th>
                            <th class="appraisal-column-view"></th>
                        </tr>
                    </thead>
                <?php endif; ?>
                <tbody>
                <?php foreach ($rows as $row_count => $row): ?>
                    <?php $class = 'appraisal-row appraisal-filter-type-' . _get_appraisal_manager_filter_type($row['status_id'], $row['self_appraisal_due'], $row['manager_appraisal_due'], $row['grade_due']); ?>
                    <tr id="row-appraisal-id-<?php print strip_tags($row['id']); ?>" <?php if ($row_classes[$row_count] || $class) { print 'class="' . implode(' ', $row_classes[$row_count]) . ' ' . $class . '"';  } ?>">
                        <?php foreach ($row as $field => $content): ?>
                            <td <?php if ($field_classes[$field][$row_count]) { print 'class="'. $field_classes[$field][$row_count] . '" '; } ?><?php print drupal_attributes($field_attributes[$field][$row_count]); ?>>
                                <?php print strip_tags(html_entity_decode($content)); ?>
                            </td>
                        <?php endforeach; ?>
                            <td>
                                view self appraisal
                            </td>
                            <td class="appraisal-column-view-manager-appraisal">
                                view manager appraisal
                            </td>
                            <td class="appraisal-column-upload-appraisal">
                                upload appraisal
                            </td>
                            <td class="appraisal-column-view">
                                <a href="/civi_appraisals/nojs/edit/<?php print strip_tags($row['id']); ?>"
                                    class="ctools-use-modal ctools-modal-civihr-custom-style ctools-use-modal-processed">
                                    view
                                </a>
                            </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    (function($){
        var $navDocFilter = $('#nav-appraisals-filter'),
            $dropdownFilter = $('#select-appraisals-filter'),
            $tableDocStaff = $('#appraisals-manager-table'),
            $tableDocStaffRows = $tableDocStaff.find('.appraisal-row');

        var $selectedRowFilter =  $tableDocStaff.find('.appraisal-row'),
            selectedRowFilterSelector = null;

        $navDocFilter.find('a').bind('click', function(e) {
            e.preventDefault();

            var $this = $(this),
                appraisalFilter = $this.data('appraisalFilter');

            $navDocFilter.find('> li').removeClass('active');
            $this.parent().addClass('active');

            if (!appraisalFilter) {
                $selectedRowFilter = $tableDocStaff.find('.appraisal-row');
                selectedRowFilterSelector = '.appraisal-row';
            } else {
                $selectedRowFilter = $tableDocStaff.find('.appraisal-filter-type-' + appraisalFilter);
                selectedRowFilterSelector = '.appraisal-filter-type-' + appraisalFilter;
            }

            showFilteredAppraisalRows();
        });

        $dropdownFilter.on('change', function (e) {
            var appraisalFilter = $(this).val();

            if (parseInt(appraisalFilter, 10) === 0) {
                $selectedRowFilter = $tableDocStaff.find('.appraisal-row');
                selectedRowFilterSelector = '.appraisal-row';
            } else {
                $selectedRowFilter = $tableDocStaff.find('.appraisal-filter-type-' + appraisalFilter);
                selectedRowFilterSelector = '.appraisal-filter-type-' + appraisalFilter;
            }

            showFilteredAppraisalRows();
        });

        var chk = CRM.$('.checkbox-appraisal-completed');
        chk.unbind('change').bind('change', function(e) {
            var checkedAppraisalId = $(this).val();
            $.ajax({
                url: '/civi_appraisals/ajax/complete/' + checkedAppraisalId,
                success: function(result) {
                    if (!result.success) {
                        CRM.alert(result.message, 'Error', 'error');
                        return;
                    }
                    $('#row-appraisal-id-' + checkedAppraisalId).fadeOut(500, function() {
                        $(this).remove();
                        refreshAppraisalsCounter();
                    });
                }
            });
        });

        function showFilteredAppraisalRows() {
            $tableDocStaffRows.hide();
            $tableDocStaffRows.removeClass('selected-by-filter');
            $selectedRowFilter.addClass('selected-by-filter');
            $('.selected-by-filter', $tableDocStaff).show();
        }

        function refreshAppraisalsCounter() {
            var sum = 0;
            for (var i = 1; i < <?php print count($filters); ?>; i++) {
                var counter = $('.appraisal-filter-type-' + i, $tableDocStaff).length;
                sum += counter;
                $('#nav-appraisals-filter .appraisal-counter-filter-' + i).text(counter);
            }
            $('#nav-appraisals-filter .appraisal-counter-filter-0').text(sum);
        }
        
        showFilteredAppraisalRows();
        refreshAppraisalsCounter();
        
    }(CRM.$));
</script>

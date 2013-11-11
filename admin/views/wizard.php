<?php
    global $wpdb;

    if ( !isset( $wizard_i18n ) || !isset( $wizard_migration ) || !isset( $wizard_rows ) )
        pods_error( __( 'Invalid Pods Migration Wizard usage', 'pods' ) );
?>
<div class="wrap pods-admin">
    <script>
        var PODS_URL = '<?php echo PODS_URL; ?>';
    </script>
    <div id="icon-pods" class="icon32"><br /></div>

    <h2 class="italicized"><?php echo $wizard_i18n[ 'title' ]; ?></h2>

    <img src="<?php echo PODS_URL; ?>ui/images/pods-logo-notext-rgb-transparent.png" class="pods-leaf-watermark-right" />

    <div id="pods-wizard-box" class="pods-wizard-steps-3" data-action="<?php echo ( isset( $wizard_ajax_action ) ? $wizard_ajax_action : 'pods_admin' ); ?>" data-method="<?php echo ( isset( $wizard_ajax_method ) ? $wizard_ajax_method : 'migrate' ); ?>" data-migration="<?php echo $wizard_migration; ?>" data-_wpnonce="<?php echo wp_create_nonce( 'pods-migrate' ); ?>">
        <div id="pods-wizard-heading">
            <ul>
                <li class="pods-wizard-menu-current" data-step="1">
                    <i></i> <span>1</span> <?php echo pods_var_raw( 'step1', $wizard_i18n, __( 'Getting Started', 'pods' ), null, true ); ?>
                    <em></em>
                </li>
                <li data-step="2">
                    <i></i> <span>2</span> <?php echo pods_var_raw( 'step1', $wizard_i18n, __( 'Prepare', 'pods' ), null, true ); ?>
                    <em></em>
                </li>
                <li data-step="3">
                    <i></i> <span>3</span> <?php echo pods_var_raw( 'step1', $wizard_i18n, __( 'Migrate', 'pods' ), null, true ); ?>
                    <em></em>
                </li>
            </ul>
        </div>
        <div id="pods-wizard-main">

            <!-- Getting Started Panel -->
            <div id="pods-wizard-panel-1" class="pods-wizard-panel">
                <div class="pods-wizard-content pods-wizard-grey">
                    <?php echo wpautop( $wizard_i18n[ 'step1_description' ] ); ?>
                </div>
            </div>
            <!-- // Getting Started Panel -->

            <!-- Prepare Panel -->
            <div id="pods-wizard-panel-2" class="pods-wizard-panel">
                <div class="pods-wizard-content">
                    <?php echo wpautop( $wizard_i18n[ 'step2_description' ] ); ?>
                </div>
                <table cellpadding="0" cellspacing="0">
                    <col style="width: 70px">
                    <col style="width: 110px">
                    <col style="width: 580px">
                    <thead>
                        <tr>
                            <th colspan="3">
                                <?php echo pods_var_raw( 'step2_heading', $wizard_i18n, __( 'Preparing Your Content for Migration', 'pods' ) . '..', null, true ); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            foreach ( $wizard_rows as $object => $label ) {
                                if ( is_array( $label ) ) {
                                    foreach ( $label as $row_object => $row_label ) {
                        ?>
                            <tr class="pods-wizard-table-pending" data-migrate="<?php echo $object; ?>" data-object="<?php echo $row_object; ?>">
                                <td class="pods-wizard-right pods-wizard-status">
                                    <i><img src="<?php echo PODS_URL; ?>ui/images/spinner.gif" alt="Loading..." /></i>
                                </td>
                                <td class="pods-wizard-right pods-wizard-count">&mdash;</td>
                                <td class="pods-wizard-name">
                                    <?php echo $row_label; ?>
                                    <span class="pods-wizard-info"></span>
                                </td>
                            </tr>
                        <?php
                                    }
                                }
                                else {
                        ?>
                            <tr class="pods-wizard-table-pending" data-migrate="<?php echo $object; ?>">
                                <td class="pods-wizard-right pods-wizard-status">
                                    <i><img src="<?php echo PODS_URL; ?>ui/images/spinner.gif" alt="Loading..." /></i>
                                </td>
                                <td class="pods-wizard-right pods-wizard-count">&mdash;</td>
                                <td class="pods-wizard-name">
                                    <?php echo $label; ?>
                                    <span class="pods-wizard-info"></span>
                                </td>
                            </tr>
                        <?php
                                }
                            }
                        ?>
                    </tbody>
                </table>
            </div>
            <!-- // Prepare Panel -->

            <!-- Migrate Panel -->
            <div id="pods-wizard-panel-3" class="pods-wizard-panel">
                <div class="pods-wizard-content">
                    <?php echo wpautop( $wizard_i18n[ 'step3_description' ] ); ?>
                </div>
                <table cellpadding="0" cellspacing="0">
                    <col style="width: 70px">
                    <col style="width: 110px">
                    <col style="width: 580px">
                    <thead>
                        <tr>
                            <th colspan="3">
                                <?php echo pods_var_raw( 'step3_heading', $wizard_i18n, __( 'Migrating Your Content', 'pods' ) . '..', null, true ); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody><!-- complete|pending|active <i></i> -->
                        <?php
                            if ( !isset( $wizard_migrate_rows ) )
                                $wizard_migrate_rows = $wizard_rows;

                            foreach ( $wizard_migrate_rows as $object => $label ) {
                                if ( is_array( $label ) ) {
                                    foreach ( $label as $row_object => $row_label ) {
                        ?>
                            <tr class="pods-wizard-table-pending" data-migrate="<?php echo $object; ?>" data-object="<?php echo $row_object; ?>">
                                <td class="pods-wizard-right pods-wizard-status">
                                    <i><img src="<?php echo PODS_URL; ?>ui/images/spinner.gif" alt="Loading..." /></i>
                                </td>
                                <td class="pods-wizard-right pods-wizard-count">&mdash;</td>
                                <td class="pods-wizard-name">
                                    <?php echo $row_label; ?>
                                    <span class="pods-wizard-info"></span>
                                </td>
                            </tr>
                        <?php
                                    }
                                }
                                else {
                        ?>
                            <tr class="pods-wizard-table-pending" data-migrate="<?php echo $object; ?>">
                                <td class="pods-wizard-right pods-wizard-status">
                                    <i><img src="<?php echo PODS_URL; ?>ui/images/spinner.gif" alt="Loading..." /></i>
                                </td>
                                <td class="pods-wizard-right pods-wizard-count">&mdash;</td>
                                <td class="pods-wizard-name">
                                    <?php echo $label; ?>
                                    <span class="pods-wizard-info"></span>
                                </td>
                            </tr>
                        <?php
                                }
                            }

                            if ( isset( $wizard_cleanup ) && false !== $wizard_cleanup ) {
                        ?>
                            <tr class="pods-wizard-table-pending" data-migrate="cleanup">
                                <td class="pods-wizard-right pods-wizard-status">
                                    <i><img src="<?php echo PODS_URL; ?>ui/images/spinner.gif" alt="Loading..." /></i>
                                </td>
                                <td class="pods-wizard-right pods-wizard-count">&mdash;</td>
                                <td class="pods-wizard-name">
                                    <?php echo pods_var_raw( 'cleanup', $wizard_i18n, __( 'Cleanup', 'pods' ), null, true ); ?>
                                    <span class="pods-wizard-info"></span>
                                </td>
                            </tr>
                        <?php
                            }
                        ?>
                    </tbody>
                </table>
            </div>
            <!-- // Mirate Panel -->

        </div>
        <div id="pods-wizard-actions">
            <div id="pods-wizard-toolbar">
                <a href="#start" id="pods-wizard-start" class="button button-secondary"><?php echo pods_var_raw( 'button_start_over', $wizard_i18n, __( 'Start Over', 'pods' ), null, true ); ?></a> <a href="#next" id="pods-wizard-next" class="button button-primary" data-next="<?php echo esc_attr( pods_var_raw( 'button_next', $wizard_i18n, __( 'Next Step', 'pods' ), null, true ) ); ?>" data-finished="<?php echo esc_attr( pods_var_raw( 'button_finished', $wizard_i18n, __( 'Go to Pods Admin', 'pods' ), null, true ) ); ?>"><?php echo pods_var_raw( 'button_start', $wizard_i18n, __( 'Next Step', 'pods' ), null, true ); ?></a>
            </div>
            <div id="pods-wizard-finished">
                <?php echo pods_var_raw( 'complete', $wizard_i18n, __( 'Migration Complete!', 'pods' ), null, true ); ?>
            </div>
        </div>
    </div>
</div>

<script>
    var pods_admin_wizard_callback = function ( step ) {
        jQuery( '#pods-wizard-start, #pods-wizard-next' ).hide();

        if ( step == 2 ) {
            jQuery( '#pods-wizard-box' ).PodsMigrate( 'prepare' );

            return false;
        }
        else if ( step == 3 ) {
            jQuery( '#pods-wizard-box' ).PodsMigrate( 'migrate' );
        }
    }

    jQuery( function ( $ ) {
        $( '#pods-wizard-box' ).Pods( 'wizard' );
    } );
</script>

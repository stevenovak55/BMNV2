<?php
/**
 * Footer template.
 *
 * @package BMN_Theme
 */

?>
    <footer id="site-footer" class="site-footer" role="contentinfo">
        <div class="site-footer__inner">
            <nav class="footer-navigation" aria-label="<?php esc_attr_e('Footer Navigation', 'bmn-theme'); ?>">
                <?php
                wp_nav_menu([
                    'theme_location' => 'footer',
                    'menu_class'     => 'footer-menu',
                    'container'      => false,
                    'depth'          => 1,
                    'fallback_cb'    => false,
                ]);
                ?>
            </nav>

            <div class="site-info">
                <p>&copy; <?php echo esc_html(date_i18n('Y')); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
</div><!-- #page -->

<?php wp_footer(); ?>
</body>
</html>

<?php
/** Página não encontrada com o layout novo do Conexão Pop. */
if (!defined('ABSPATH')) {
	exit;
}
get_header();
echo CPH::html_404(); // phpcs:ignore -- HTML montado e escapado em CPH_Paginas
get_footer();

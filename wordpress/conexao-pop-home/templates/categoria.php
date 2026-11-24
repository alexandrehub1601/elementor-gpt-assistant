<?php
/** Categoria com o layout novo do Conexão Pop. Usa o cabeçalho e o rodapé do tema. */
if (!defined('ABSPATH')) {
	exit;
}
get_header();
echo CPH::html_categoria(); // phpcs:ignore -- HTML montado e escapado em CPH_Paginas
get_footer();

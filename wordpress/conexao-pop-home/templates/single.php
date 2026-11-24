<?php
/** Matéria com o layout novo do Conexão Pop. Usa o cabeçalho e o rodapé do tema. */
if (!defined('ABSPATH')) {
	exit;
}
get_header();
while (have_posts()) {
	the_post();
	echo CPH::html_materia(get_post()); // phpcs:ignore -- HTML montado e escapado em CPH_Paginas
}
get_footer();

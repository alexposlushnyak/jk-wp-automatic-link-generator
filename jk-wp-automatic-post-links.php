<?php defined('ABSPATH') || exit;

class jk_wp_automatic_post_links
{

    public function generator()
    {

        apply_filters('the_content', [$this, 'autolinks_generator_handler']);

    }

    public function autolinks_generator_handler($content)
    {

        $main_post_id = get_the_ID();

        $allowed_tags = array('div');

        $allowed_classes = array('some', 'class');

        if (empty($allowed_tags)):

            return $content;

        endif;

        if (empty($allowed_classes[0])):

            $allowed_classes = array();

        endif;

        $cached_titles = get_option('autolinks_array_' . $main_post_id);

        $cache = false;

        $current_date = new DateTime(date('Y-m-d-G'));

        $expire_date = get_option('expire_date_autolinks_array_' . $main_post_id);

        if (empty($cached_titles) || $current_date >= $expire_date || empty($expire_date)):

            $post_titles = get_option('posts_titles');

            $result_titles = wp_list_pluck($post_titles, 'post_title', 'post_ID');

            $match_titles = array_slice($result_titles, 0, count($post_titles));

            $expire_date = new DateTime(date('Y-m-d-G'));

            $expire_date->modify('+48 hours');

            update_option('expire_date_autolinks_array_' . $main_post_id, $expire_date, false);

        else:

            $match_titles = $cached_titles;

            $cache = true;

        endif;

        $result_titles['current_title'] = get_the_ID();

        foreach ($match_titles as $title):

            if ($cache):

                $id = $title['id'];

                $title = $title['title'];

            else:

                $id = false;

            endif;

            $content = preg_replace_callback('#({[^{}].*}(*SKIP)(*F)|[^-=/>\]]\b' . strval($title) . '\b[^-<[](?=[^>]*(<|$)))#siu', function ($matches) use ($result_titles, $main_post_id, $id, $cache) {

                $string = preg_replace('/[^a-zA-Z0-9]+/', '', $matches[0]);

                $post_id = array_search(strtolower($string), array_map('strtolower', $result_titles));

                $cached_titles = get_option('autolinks_array_' . $post_id);

                if (empty($post_id)):

                    $post_id = $id;

                endif;

                if ($result_titles['current_title'] !== $post_id && !empty($post_id)):

                    if (!$cache):

                        if (empty($cached_titles)):

                            $cached_titles = array();

                        endif;

                        array_push($cached_titles, array(
                            'title' => get_the_title($post_id),
                            'id' => $post_id
                        ));

                    endif;

                    update_option('autolinks_array_' . $main_post_id, $cached_titles, false);

                    return '<span class="auto-post-link" href="' . get_permalink($post_id) . '"> ' . esc_html(ucfirst(get_the_title($post_id))) . ' </span>';

                else:

                    return $matches[0];

                endif;

            }, $content);

        endforeach;

        $html = new simple_html_dom();

        $html->load($content);

        $ready_elements = array();

        foreach ($html->find('span.auto-post-link') as $el):

            if ((!in_array($el->parent()->tag, $allowed_tags) && !in_array($el->parent()->class, $allowed_classes))
                || (!in_array($el->parent()->class, $allowed_classes) && !in_array($el->parent()->tag, $allowed_tags))):

                $el->outertext = $el->innertext;

            endif;

        endforeach;

        foreach ($html->find('span.auto-post-link') as $el):

            $el->outertext = str_replace('<span', '<a', $el->outertext);

            $el->outertext = str_replace('span>', 'a>', $el->outertext);

        endforeach;

        $html_new = new simple_html_dom();

        $html_new->load($html);

        foreach ($html_new->find('a.auto-post-link') as $el):

            if ($el->parent()->class === 'intro'):

                $el->outertext = strip_tags($el->outertext);

            else:

                if (!in_array(strip_tags($el->outertext), $ready_elements)) :

                    array_push($ready_elements, strip_tags($el->outertext));

                else :

                    $el->outertext = strip_tags($el->outertext);

                endif;

            endif;

        endforeach;

        return $html_new;

    }

}

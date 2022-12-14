<?php
require_once(ABSPATH . 'wp-custom-api-users.php');

add_action('rest_api_init', function () {
    // Get Image thumbnail by featured_media id
    register_rest_field(
        'post',
        'images', // vị trí tùy chọn
        array('get_callback' => 'getMediaUrl')
    );

    register_rest_field(
        array('post', 'comment'),
        'authorData',
        array('get_callback' => 'getPostData')
    );

    register_rest_field(
        'post',
        'viewCount',
        array('get_callback' => 'getViewCount')
    );

    register_rest_field(
        'post',
        'commentCount',
        array('get_callback' => 'getCommentCount')
    );

    register_rest_field(
        'comment',
        'commentReplyCount',
        array('get_callback' => 'getCommentReplyCount')
    );
});

add_action('rest_api_init', function(){
    $wpRoles = wp_roles();
    $capSubscriber = $wpRoles->get_role('subscriber');

    if(!$capSubscriber->capabilities['upload_files']){
        $wpRoles->add_cap('subscriber', 'upload_files');
    }
});

function getMediaUrl($post, $field_name, $request)
{
    $postId = $post['id'];

    if ($postId) {
        $url = get_the_post_thumbnail_url($postId);

        return $url;
    }

    return '';
};

function getPostData($post, $field_name, $request)
{

    $authorId = $post['author'];

    if ($authorId) {
        return array(
            'authorId' => $authorId,
            'nickName' => get_the_author_meta('nickname', $authorId),
            'description' => get_the_author_meta('description', $authorId),
            'authorAvatar' => get_user_meta($authorId, 'simple_local_avatar')[0]['full']
        );
    };

    return array(
        'author' => '',
        'nickName' => '',
        'description' => '',
        'authorAvatar' => ''
    );
};


function getViewCount($post, $field_name, $request)
{
    $postId = $post['id'];

    if (function_exists('pvc_get_post_views') and $postId) {
        $viewCount = pvc_get_post_views($postId);

        return $viewCount;
    }

    return 0;
};

function getCommentCount($post, $field_name, $request)
{
    $postId = $post['id'];

    $commentCount = get_comments_number($postId);

    return (int)$commentCount;
};

function getCommentReplyCount($comment, $field_name, $request)
{
    $commentParentId = $comment['id'];
    $postId = $comment['post'];

    if ($comment['parent'] === 0) {
        global $wpdb;

        $query = "SELECT COUNT(comment_ID) AS replyCount FROM $wpdb->comments WHERE `comment_approved` = 1 AND `comment_post_ID` = $postId AND `comment_parent` = $commentParentId";

        $data = $wpdb->get_row($query);

        return (int)$data->replyCount;
    }

    return 0;
};

add_filter('rest_endpoints', function ($routes) {

    if (!$routes['/wp/v2/posts'][0]['args']['orderby']['enum']) {
        return $routes;
    }

    array_push($routes['/wp/v2/posts'][0]['args']['orderby']['enum'], 'post_views');

    return $routes;
});

add_filter('rest_prepare_user', function($response, $user, $request){

    $data = $response->get_data();
    $user_id = $data['id'];

    if($user_id){
        $data['email'] = $user->data->user_email;
        $data['user_name'] = $user->data->user_login;
        $data['nickname'] = get_user_meta($user_id, 'nickname')[0];
        $data['first_name'] = get_user_meta($user_id, 'first_name')[0];
        $data['last_name'] = get_user_meta($user_id, 'last_name')[0];
    }

    $response = rest_ensure_response($data);

    return $response;
}, 10, 3);



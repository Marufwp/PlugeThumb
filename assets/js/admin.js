(function ($) {
    'use strict';

    $(document).ready(function () {
        var videoFrame;
        $('#plugethumb_video_upload').on('click', function (e) {
            e.preventDefault();

            if (videoFrame) {
                videoFrame.open();
                return;
            }

            videoFrame = wp.media({
                title: 'Select or Upload Video',
                button: { text: 'Use this video' },
                library: { type: 'video' },
                multiple: false
            });

            videoFrame.on('select', function () {
                var att = videoFrame.state().get('selection').first().toJSON();
                $('#plugethumb_video_id').val(att.id);
                $('#plugethumb_video_url').val(att.url).show();
                $('#plugethumb_video_preview').attr('src', att.url).show();
            });

            videoFrame.open();
        });

        $('#plugethumb_video_remove').on('click', function (e) {
            e.preventDefault();
            $('#plugethumb_video_id').val('');
            $('#plugethumb_video_url').val('').hide();
            $('#plugethumb_video_preview').attr('src', '').hide();
        });

        var imageFrame;
        $('#plugethumb_media_upload').on('click', function (e) {
            e.preventDefault();

            if (imageFrame) {
                imageFrame.open();
                return;
            }

            imageFrame = wp.media({
                title: 'Select or Upload Image',
                button: { text: 'Use this image' },
                library: { type: 'image' },
                multiple: false
            });

            imageFrame.on('select', function () {
                var att = imageFrame.state().get('selection').first().toJSON();
                $('#plugethumb_image_url').val(att.url);
                $('#plugethumb_image_preview').attr('src', att.url).show();
            });

            imageFrame.open();
        });
    });
})(jQuery);

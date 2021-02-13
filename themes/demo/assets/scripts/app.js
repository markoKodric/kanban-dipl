(function ($) {
    function init() {
        initTimer();
        initToggle();
        initWorkflow();
        initWorkflowScroll();
        initTicketSorting();
        initFileInput();
        initCustomInputs();

        $('[data-request-blur]').unbind('blur').on('blur', function () {
            if ($(this).attr('data-request')) {
                $(this).request($(this).attr('data-request'));
            } else {
                $(this).parents('form').request();
            }
        });
    }

    function initCustomInputs() {
        $('input.custom:not([type="date"]):not(.not-dynamic)').each(function() {
            let textLength = $(this).val().replace(/\s+/gm, '').length;
            let endSpaces = $(this).val().match(/\s+$/gm);

            if (endSpaces) {
                textLength += endSpaces[0].length;
            }

            $(this).width(textLength + 'ch');
        });
        $('input.custom:not([type="date"]):not(.not-dynamic)').unbind().on('input', function() {
            let textLength = $(this).val().replace(/\s+/gm, '').length;
            let endSpaces = $(this).val().match(/\s+$/gm);

            if (endSpaces) {
                textLength += endSpaces[0].length;
            }

            $(this).width(textLength + 'ch');
        });
    }

    function initFileInput() {
        var $inputWrapper = $(".input-type-file");
        var $fileInput = $(
            ".input-type-file input[type=file]:not([data-inputfile-attached])"
        );
        $inputWrapper.removeClass("is-focused");
        $fileInput.each(function (e) {
            $(this).attr("data-inputfile-attached", true);
            $(this).on("change", function () {
                var filename = $(this)[0].files.length
                    ? $(this)[0].files[0].name
                    : "";
                if (filename != "") {
                    $(this)
                        .next(".input-type-file-text")
                        .text(filename);
                    $(this).parents('.form-item').addClass('is-active');
                } else {
                    $(this).parents('.form-item').removeClass('is-active');
                }

                var element = $(this).parents('.form-item').find('img');

                if ($(this)[0].files.length && element.length) {
                    var reader = new FileReader();

                    reader.onload = function(e) {
                        element.attr('src', e.target.result);
                    }

                    reader.readAsDataURL($(this)[0].files[0]);
                }
            });
            $(this).on("click", function () {
                $(this)
                    .closest($inputWrapper)
                    .addClass("is-focused");
            });
        });
        $(document).on("click", function (e) {
            if (
                (!$inputWrapper.is(e.target) &&
                    $inputWrapper.has(e.target).length === 0 &&
                    $("#toggleSearch").has(e.target).length === 0) ||
                ($inputWrapper.is(e.target) &&
                    $inputWrapper.has(e.target).length === 0)
            ) {
                $inputWrapper.removeClass("is-focused");
            }
        });
    }

    let interval;
    function initTimer() {
        $('.timer').unbind('click').on('click', function () {
            let $self = $(this);

            $self.parent().siblings('.elapsed-time').toggleClass('timer-active');
            $self.find('i').toggleClass('la-pause').toggleClass('la-clock');

            if ($self.parent().siblings('.elapsed-time').hasClass('timer-active')) {
                updateTimer();
                interval = setInterval(updateTimer, 1000);
            } else {
                clearInterval(interval);
            }

            function updateTimer() {
                let currentSecs = parseInt($self.siblings('[name="time"]').val());

                $self.siblings('[name="time"]').val(parseInt($self.siblings('[name="time"]').val()) + 1);

                $self.parent().siblings('.elapsed-time').html(new Date(currentSecs * 1000).toISOString().substr(11, 8));
            }
        })
    }

    function initToggle() {
        $('[data-target]').unbind().on('click', function(e) {
            e.preventDefault();

            let target = $(this).attr('data-target');

            $(target).toggleClass('is-active');
            $('[data-target="' + target + '"]').toggleClass('is-active');
        });
    }

    function initWorkflowScroll() {
        let el = document.querySelector('#workflow');
        let x = 0, y = 0, top = 0, left = 0;

        if (el) {
            let draggingFunction = (e) => {
                document.addEventListener('mouseup', () => {
                    el.style.cursor = 'default';
                    document.removeEventListener("mousemove", draggingFunction);
                });

                el.style.cursor = 'move';
                el.scrollLeft = left - e.pageX + x;
                el.scrollTop = top - e.pageY + y;
            };

            el.addEventListener('mousedown', (e) => {
                if ($(e.target).parents('.ticket').length || $(e.target).parents('.flow-definer-wrapper').length) return;

                e.preventDefault();

                y = e.pageY;
                x = e.pageX;
                top = el.scrollTop;
                left = el.scrollLeft;

                document.addEventListener('mousemove', draggingFunction);
            });
        }
    }

    function initWorkflow() {
        $('.flow-definer-wrapper').find('.background-overlay').unbind().on('click', function (e) {
            e.preventDefault();

            $(this).parent().removeClass('is-active');
        });

        $('.mock-sections').sortable({
            items: ".mock-section:not(.inner-mock-section)",
            cancel: "input",
            placeholder: "ui-state-highlight",
            tolerance: 'pointer',
            cursor: 'move',
            start: function (event, ui) {
                $(".ui-state-highlight").css({
                    'width': $(ui.item).css('width'),
                    'height': $(ui.item).css('height'),
                    'margin-top': 0
                });
            },
            stop: function (event, ui) {
                $('[name="sections"]').val(__parseSections());
            }
        });

        let mockSectionTemplate =
            '<div class="mock-section mx-3">\n' +
            '    <div class="section-header">\n' +
            '        <input type="text" class="custom" value="Title">\n' +
            '    </div>\n' +
            '    <div class="section-body">\n' +
            '        <div class="wip-limit flex items-center justify-center">' +
            '           <span class="whitespace-nowrap mr-2">WIP Limit:</span>' +
            '           <input type="text" value="-" class="custom">' +
            '       </div>' +
            '        <div class="section-actions">' +
            '           <a class="button button-icon button-tooltip add-subsections" data-tooltip="Add subsection"><i class="text-2xl la la-plus"></i></a>\n' +
            '           <div class="all-members"></div>\n' +
            '           <div class="section-members"></div>\n' +
            '       </div>' +
            '        <a class="button button-icon bg-custom bg-red-500 hover:shadow-red button-tooltip remove-section" data-tooltip="Remove section"><i class="la la-times"></i></a>' +
            '    </div>\n' +
            '</div>';

        $('#add-section').unbind().on('click', function (e) {
            e.preventDefault();

            let newSection = $(mockSectionTemplate);

            $(this).before(newSection);

            $('[name="sections"]').val(__parseSections());

            initCustomInputs();
            initWorkflow();
        });

        $('.add-subsections').unbind().on('click', function (e) {
            e.preventDefault();

            let newSection = $(mockSectionTemplate).addClass('inner-mock-section').removeClass('mx-3');

            $(this).parents('.section-body').first().prepend(newSection);

            $(this).parents('.mock-section').first().addClass('has-children');

            $('[name="sections"]').val(__parseSections());

            initCustomInputs();
            initWorkflow();
        });

        $('.remove-section').unbind().on('click', function (e) {
            e.preventDefault();

            if ($(this).parents('.section-body').first().find('.mock-section').length === 0) {
                $(this).parents('.mock-section.has-children').first().removeClass('has-children');
            }

            $(this).parents('.mock-section').first().remove();

            $('[name="sections"]').val(__parseSections());

            initWorkflow();
        });

        $('.mock-section').find('.section-header').children('input').on('input keyup paste', function () {
            $('[name="sections"]').val(__parseSections());
        });

        $('.mock-section').find('.section-body').children('.wip-limit').children('input').on('input keyup paste', function () {
            $('[name="sections"]').val(__parseSections());
        });
    }

    function initTicketSorting() {
        $(".section-tickets-inner").sortable({
            connectWith: ".section-tickets-inner",
            opacity: 0.75,
            revert: 150,
            scroll: true,
            containment: $('#workflow'),
            placeholder: "ui-state-highlight",
            tolerance: 'pointer',
            cursor: 'move',
            stop: function(event, ui) {
                let $sectionId = $(ui.item).parents('.flow-section').attr('data-section-id');
                let $sectionTickets = $(ui.item).parents('.section-tickets-inner').sortable('toArray', {attribute: 'data-id'});

                $(ui.item).parents('.flow-section').first().request('onReorderTickets', {
                    data: {
                        sectionId: $sectionId,
                        tickets: $sectionTickets
                    }
                });
            },
            start: function( event, ui ) {
                $(ui.item).off('click');
                $(".ui-state-highlight").css({
                    'width': $(ui.item).css('width'),
                    'height': $(ui.item).css('height'),
                    'margin-top': 0
                });
            }
        }).disableSelection();
    }

    $(document).ready(init);
    $(document).render(init);
}(jQuery));

function hexToRgb(hex) {
    return hex.replace(/^#?([a-f\d])([a-f\d])([a-f\d])$/i
        ,(m, r, g, b) => '#' + r + r + g + g + b + b)
        .substring(1).match(/.{2}/g)
        .map(x => parseInt(x, 16))
        .join(',')
}

function __parseSections() {
    let sections = [];

    $('.mock-sections').children('.mock-section').each(function (index, item) {
        let section = {};

        if ($(item).hasClass('has-children')) {
            let subsections = [];

            section.id = $(item).attr('data-id');
            section.title = $(item).children('.section-header').first().children('input').first().val().trim();

            $(item).find('.mock-section').each(function () {
                let subsection = {};

                subsection.id = $(this).attr('data-id');
                subsection.title = $(this).children('.section-header').first().children('input').first().val().trim();
                subsection.wipLimit = $(this).children('.section-body').first().children('.wip-limit').first().find('input').val().trim();

                subsections.push(subsection);
            });

            section.subsections = subsections;
        } else {
            section.id = $(item).attr('data-id');
            section.title = $(item).children('.section-header').first().children('input').first().val().trim();
            section.wipLimit = $(item).children('.section-body').first().children('.wip-limit').first().find('input').val().trim();
            section.subsections = [];
        }

        sections[index] = section;
    });

    return JSON.stringify(sections);
}
(function ($) {
    function init() {
        initToggle();
        initWorkflow();
        initWorkflowScroll();
        initTicketSorting();
        initFileInput();
        initCustomInputs();
        initCFD();
        initCC();
        initTimer();
        initNotifications();

        $('[data-request-blur]').unbind('blur').on('blur', function () {
            if ($(this).attr('data-request')) {
                $(this).request($(this).attr('data-request'));
            } else {
                $(this).parents('form').request();
            }
        });
    }

    function initOnce() {
        initSocketIO();

        /*window.onpopstate = function(event) {
            const queryString = window.location.search;
            const urlParams = new URLSearchParams(queryString);
            console.log(queryString, window.location.hash);
            if (urlParams.has('ticket')) {
                const ticket = urlParams.get('ticket');
                if (ticket && window.location.hash == '#open-ticket-' + ticket) {
                    $('#ticket-' + ticket).removeAttr('data-popup-attached');
                    $('#ticketPopup').removeClass('is-active');
                    $('#ticketPopup *[data-target="#ticketPopup"]').removeClass('is-active');
                    $('#ticket-' + ticket + ':not([data-popup-attached])').attr('data-popup-attached', true).trigger('click');
                } else {
                    $('#ticket-' + ticket).removeAttr('data-popup-attached');
                    $('#ticketPopup').removeClass('is-active');
                    $('#ticketPopup *[data-target="#ticketPopup"]').removeClass('is-active');
                }
            } else {
                $('#ticketPopup').removeClass('is-active');
                $('#ticketPopup *[data-target="#ticketPopup"]').removeClass('is-active');
            }
        };

        if (window.location.hash.startsWith('#open-ticket-')) {
            let splitHash = window.location.hash.split('-');

            $('#ticket-' + splitHash[splitHash.length - 1] + ':not([data-popup-attached])').attr('data-popup-attached', true).trigger('click');
        }*/
    }

    function initSocketIO() {
        let app = $('#app');
        
        const tid = app.attr('data-tid');
        const uid = app.attr('data-uid');
        let socket;

        if (typeof io === 'function') {
            socket = io(app.attr('data-sio'));

            if (tid) {
                socket.emit('userConnection', {
                    tid: tid
                });

                socket.on('triggerUpdate', function (data) {
                    $.request('onSocketEvent', {
                        data: data,
                        success: function (result) {
                            for (const [key, value] of Object.entries(result)) {
                                if (!$(key.replace('@', '').replace('-#', '#')).length) {
                                    continue;
                                }

                                if (key === '@#js-notifications' && $('#js-notifications')[0].innerHTML.indexOf(result.notification) === -1) {
                                    $(key.replace('@', '')).append(value);
                                } else if (key.startsWith('@') && key !== '@#js-notifications') {
                                    $(key.replace('@', '')).append(value);
                                } else if (key === '-#js-checklists') {
                                    $(key.replace('-#', '#')).find('#checklist-' + data.checklist).remove();
                                } else if (key !== '@#js-notifications') {
                                    $(key).html(value);
                                }
                            };

                            init();
                        }
                    })
                });
            }
        }

        $(document).on('socketEmit', function (event, name, params) {
            const defaultParams = {
                tid: tid,
                uid: uid
            };

            if (socket) {
                socket.emit(name, {...defaultParams, ...params});
            }
        });
    }

    function initCustomInputs() {
        let dynamicInputs = $('input.custom:not([type="date"]):not(.not-dynamic)');

        dynamicInputs.each(function() {
            __handleDynamicInput($(this));
        });
        
        dynamicInputs.unbind().on('input', function() {
            __handleDynamicInput($(this));
        });
        
        function __handleDynamicInput(item) {
            let textLength = item.val().replace(/\s+/gm, '').length;
            let endSpaces = item.val().match(/\s+$/gm);

            if (endSpaces) {
                textLength += endSpaces[0].length;
            }

            item.width(textLength + 'ch');
        }
    }

    function initFileInput() {
        let inputWrapper = $(".input-type-file");
        let fileInput = $(".input-type-file input[type=file]:not([data-input-file-attached])");
        
        inputWrapper.removeClass("is-focused");
        
        fileInput.each(function () {
            $(this).attr("data-input-file-attached", true);
            
            $(this).on("change", function () {
                let fileInput = $(this)[0];
                
                let filename = fileInput.files.length ? fileInput.files[0].name : "";
                let formItem = $(this).parents('.form-item');
                
                if (filename.length) {
                    $(this).next(".input-type-file-text").text(filename);
                    formItem.addClass('is-active');
                } else {
                    formItem.removeClass('is-active');
                }

                let imageThumb = formItem.find('img');

                if (!imageThumb.length) {
                    imageThumb = $('<img src="" width="40" class="rounded-full"/>');
                    
                    $(this).parents('.input-type-file').prev().html(imageThumb);
                }

                if (fileInput.files.length && imageThumb.length) {
                    let reader = new FileReader();

                    reader.onload = function(e) {
                        imageThumb.attr('src', e.target.result);
                    };

                    reader.readAsDataURL(fileInput.files[0]);
                }
            });
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

                $self.siblings('[name="time"]').val(currentSecs + 1);

                $self.parent().siblings('.elapsed-time').html(new Date(currentSecs * 1000).toISOString().substr(11, 8));
            }
        });

        if ($('[name="time"].timer-active').length && $('.elapsed-time.timer-active').length) {
            let $self = $('[name="time"]');

            interval = setInterval(updateTimer, 1000);

            function updateTimer() {
                let currentSecs = parseInt($self.val());

                $self.val(currentSecs + 1);

                $self.parent().prev().html(new Date(currentSecs * 1000).toISOString().substr(11, 8));
            }
        }
    }

    function initToggle() {
        $('[data-target]').unbind().on('click', function(e) {
            e.preventDefault();

            let target = $(this).attr('data-target');

            /*if ($(target).hasClass('is-active')) {
                window.location.hash = '';
            }*/
            
            $(target).toggleClass('is-active');
            $('[data-target="' + target + '"]').toggleClass('is-active');

            if ($(this).attr('data-request')) {
                $(target).find('.ticket-wrapper').remove();
            }
        });

        $('[data-target] + .background-overlay').unbind().on('click', function (e) {
            e.preventDefault();

            let target = $(this).prev().attr('data-target');

            $(target).removeClass('is-active');
            $('[data-target="' + target + '"]').removeClass('is-active');
        });
    }

    function initWorkflowScroll() {
        const slider = $('#workflow');

        slider.off();

        if (slider.length) {
            let isDown = false;
            let startX;
            let scrollLeft;

            slider.on('mousedown', (e) => {
                if ($(e.target).hasClass('ticket') || $(e.target).parents('.ticket').length || 
                    $(e.target).parents('.flow-definer-wrapper').length || $(e.target).parents('#js-add-section').length) 
                    return;

                isDown = true;
                startX = e.pageX - slider.offset().left;
                scrollLeft = slider.scrollLeft();
            });
            slider.on('mouseleave', () => {
                isDown = false;
            });
            slider.on('mouseup', () => {
                isDown = false;
            });
            slider.on('mousemove', (e) => {
                if(!isDown) return;
                
                e.preventDefault();
                
                const x = e.pageX - slider.offset().left;
                const walk = (x - startX) * 2;
                slider.scrollLeft(scrollLeft - walk);
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

        let radioId = '_' + Math.random().toString(36).substr(2, 9);

        let mockSectionTemplate =
            '<div class="mock-section mx-3">\n' +
            '    <div class="section-header">\n' +
            '        <input type="text" class="custom" value="Title">\n' +
            '    </div>\n' +
            '    <div class="section-body">\n' +
            '        <div class="wip-limit flex items-center justify-center flex-wrap">' +
            '            <div class="flex items-center">' +
            '                <span class="whitespace-nowrap mr-2">WIP Limit:</span>' +
            '                <input type="text" value="-" class="custom">' +
            '            </div>' +
            '            <label class="w-full flex items-start text-left mt-3" for="' + radioId + '">' +
            '                <input type="radio"' +
            '                       id="' + radioId + '"' +
            '                       name="markComplete"' +
            '                       class="mr-2 w-5 h-5 min-w-5">' +
            '                <span>Mark tickets complete</span>' +
            '            </label>' +
            '        </div>' +
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
        
        let mockSections = $('.mock-section');

        mockSections.find('.section-header').children('input').on('input keyup paste', function () {
            $('[name="sections"]').val(__parseSections());
        });

        mockSections.find('.section-body').find('input[type="text"]', 'input[type="radio"]').on('input keyup paste change', function () {
            $('[name="sections"]').val(__parseSections());
        });
    }

    function initTicketSorting() {
        let startCol, stopCol, defaultSectionTickets;

        $(".section-tickets-inner.is-sortable").sortable({
            connectWith: ".section-tickets-inner:not(.wip-reached)",
            opacity: 0.75,
            revert: 150,
            scroll: true,
            containment: $('#workflow'),
            placeholder: "ui-state-highlight",
            tolerance: 'pointer',
            cursor: 'move',
            stop: function(event, ui) {
                $(ui.item).unbind('mousemove')

                stopCol = $(ui.item).parent();

                if (startCol && startCol.length && parseInt(startCol.attr('data-wip-limit')) > 0 && startCol.children().length >= parseInt(startCol.attr('data-wip-limit'))) {
                    startCol.addClass('wip-reached');
                } else if (startCol && startCol.length) {
                    startCol.removeClass('wip-reached');
                }

                if (stopCol && stopCol.length && parseInt(stopCol.attr('data-wip-limit')) > 0 && stopCol.children().length >= parseInt(stopCol.attr('data-wip-limit'))) {
                    stopCol.addClass('wip-reached');
                } else if (stopCol && stopCol.length) {
                    stopCol.removeClass('wip-reached');
                }

                let $sectionId = $(ui.item).parents('.flow-section').attr('data-section-id');
                let $sectionTickets = $(ui.item).parents('.section-tickets-inner').sortable('toArray', {attribute: 'data-id'});

                if (startCol && stopCol && startCol.attr('id') === stopCol.attr('id') && JSON.stringify($sectionTickets) === JSON.stringify(defaultSectionTickets)) return;

                $(ui.item).parents('.flow-section').first().request('onReorderTickets', {
                    data: {
                        sectionId: $sectionId,
                        tickets: $sectionTickets,
                        ticket: $(ui.item).attr('data-id')
                    },
                    complete: function (data) {
                        if (startCol.length) {
                            let startSectionId = startCol.attr('id').split('-');

                            sectionUpdate('#' + startCol.attr('id'), 'projectsingle/_tickets', startSectionId[startSectionId.length - 1], 'stop');
                        }

                        if (stopCol.length) {
                            let stopSectionId = stopCol.attr('id').split('-');

                            sectionUpdate('#' + stopCol.attr('id'), 'projectsingle/_tickets', stopSectionId[stopSectionId.length - 1], 'stop');
                        }

                    }
                });
            },
            start: function( event, ui ) {
                $(ui.item).off('click');

                /*$(ui.item).on('mousemove', function (e) {
                    if (e.pageX > $('#workflow').offset().left + $('#workflow').width() - $(this).width() && $('#workflow').scrollLeft() < $('#workflow')[0].scrollWidth) {
                        $('#workflow').scrollLeft($('#workflow').scrollLeft() + 3);
                    }
                    if (e.pageX <= $('#workflow').offset().left + $(this).width()) {
                        $('#workflow').scrollLeft($('#workflow').scrollLeft() - 3);
                    }
                })*/

                startCol = $(ui.item).parent();

                defaultSectionTickets = $(ui.item).parents('.section-tickets-inner').sortable('toArray', {attribute: 'data-id'});

                $(".ui-state-highlight").css({
                    'width': $(ui.item).css('width'),
                    'height': $(ui.item).css('height'),
                    'margin-top': 0
                });
            },
        }).disableSelection();


    }

    function initCFD() {
        let widget = $('#widget-cfd');
        
        if (widget.length && widget.attr('data-cfd').length) {
            widget.find('#container').highcharts({
                chart: {
                    type: 'area',
                    zoomType: 'x',
                    panning: true,
                    panKey: 'shift',
                    resetZoomButton: {
                        relativeTo: 'spacingBox',
                        position: {
                            y: 0,
                            x: 0
                        },
                        theme: {
                            fill: 'white',
                            'stroke-width': 1,
                            stroke: 'grey',
                            r: 0,
                            states: {
                                hover: {
                                    fill: '#b7cfec'
                                },
                                select: {
                                    stroke: '#039',
                                    fill: '#b7cfec'
                                }
                            }
                        }
                    }
                },
                title: {
                    text: ''
                },
                xAxis: {
                    title: {
                        text: 'Date',
                        margin: 24,
                        style: {
                            fontWeight: '600',
                            fontSize: '14px',
                            fontFamily: 'Nunito',
                            color: 'black'
                        }
                    },
                    labels: {
                        style: {
                            fontWeight: 'normal',
                            fontSize: '12',
                            fontFamily: 'Nunito',
                            color: 'black'
                        },
                        formatter: function () {
                            return Highcharts.dateFormat('%d', this.value) + '<br/>' + Highcharts.dateFormat('%b', this.value);
                        }
                    },
                    type: 'datetime',
                    tickInterval: JSON.parse(widget.attr('data-cfd'))[0].data.length > 10 ? 24 * 3600 * 1000 * 5 : 24 * 3600 * 1000
                },
                credits: {
                    enabled: false
                },
                yAxis: {
                    title: {
                        text: 'Number of tickets',
                        margin: 24,
                        style: {
                            fontWeight: '600',
                            fontSize: '14px',
                            fontFamily: 'Nunito',
                            color: 'black'
                        }
                    },
                    labels: {
                        style: {
                            fontWeight: 'normal',
                            fontSize: '12px',
                            fontFamily: 'Nunito',
                            color: 'black'
                        }
                    },
                    tickInterval: 3
                },
                legend: {
                    enabled: true,
                    layout: 'vertical',
                    align: 'right',
                    verticalAlign: 'middle',
                    itemMarginBottom:8,
                    itemMarginTop:8,
                    itemStyle: {
                        fontWeight: 'normal',
                        fontSize: '13',
                        fontFamily: 'Nunito'
                    }
                },
                tooltip: {
                    crosshairs: true,
                    shared: true
                },
                plotOptions: {
                    area: {
                        stacking: 'normal',
                        lineColor: '#666666',
                        lineWidth: 0,
                        marker: { enabled: false },
                        animation: {
                            duration: 600
                        }
                    },
                    series: {
                        animation: {
                            duration: 600
                        }
                    }
                },
                series: JSON.parse(widget.attr('data-cfd')),
                exporting: {
                    enabled: false
                }
            });
        }
    }

    function initCC() {
        let widget = $('#widget-cc');
        
        if (widget.length && widget.attr('data-cc').length &&
            widget.attr('data-cc-average').length && widget.attr('data-cc-rolling').length &&
            widget.attr('data-cc-standard-deviation').length) {
            let chartype = {
                type: 'scatter',
                zoomType: 'xy'
            }

            let chartxaxis = {
                title: {
                    text: 'Completion date',
                    margin: 24,
                    style: {
                        fontWeight: '600',
                        fontSize: '14px',
                        fontFamily: 'Nunito',
                        color: 'black'
                    }
                },
                labels: {
                    style: {
                        fontWeight: 'normal',
                        fontSize: '12',
                        fontFamily: 'Nunito',
                        color: 'black'
                    },
                    formatter: function () {
                        return Highcharts.dateFormat('%d', this.value) + '<br/>' + Highcharts.dateFormat('%b', this.value);
                    }
                },
                type: 'datetime',
                tickInterval: JSON.parse(widget.attr('data-cc')).length && JSON.parse(widget.attr('data-cc'))[0].length > 10 ? 24 * 3600 * 1000 * 5 : 24 * 3600 * 1000
            }

            let chartyaxis = {
                title: {
                    text: 'Days to complete',
                    margin: 24,
                    style: {
                        fontWeight: '600',
                        fontSize: '14px',
                        fontFamily: 'Nunito',
                        color: 'black'
                    }
                },
                labels: {
                    style: {
                        fontWeight: 'normal',
                        fontSize: '12px',
                        fontFamily: 'Nunito',
                        color: 'black'
                    }
                },
            }

            let chartlegend = {
                enabled: true,
                layout: 'vertical',
                align: 'right',
                verticalAlign: 'middle',
                itemMarginBottom: 8,
                itemMarginTop: 8,
                itemStyle: {
                    fontWeight: 'normal',
                    fontSize: '13',
                    fontFamily: 'Nunito'
                }
            }

            let chartplotoptions = {
                scatter: {
                    marker: {
                        radius: 5,
                        fillColor: '#FFFFFF',
                        lineWidth: 3,
                        lineColor: '#36B37E',
                        states: {
                            hover: {
                                enabled: true,
                                lineColor: '#36B37E'
                            }
                        }
                    },
                    states: {
                        hover: {
                            marker: {
                                enabled: false
                            }
                        }
                    },
                    tooltip: {
                        headerFormat: '',
                        pointFormat: "Tickets: <b>{point.name}</b><br>Completed in: <b>{point.y} days</b>",
                    },
                    animation: {
                        duration: 600
                    }
                },
                series: {
                    cluster: {
                        enabled: false,
                        minimumClusterSize: 2,
                        allowOverlap: false,
                        layoutAlgorithm: {
                            type: 'grid',
                            gridSize: 50
                        },
                        dataLabels: {
                            style: {
                                fontSize: '8px'
                            }
                        },
                        marker: {
                            fillColor: '#36B37E',
                            radius: 10,
                            states: {
                                hover: {
                                    fillColor: '#36B37E'
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 600
                    }
                }
            }

            let chartseries = [
                {
                    type: 'scatter',
                    name: 'Tickets',
                    color: '#36B37E',
                    zIndex: 10,
                    data: JSON.parse(widget.attr('data-cc'))
                },
                {
                    type: 'line',
                    name: 'Average',
                    zIndex: 8,
                    data: JSON.parse(widget.attr('data-cc-average')),
                    color: '#0263e0',
                    marker: {
                        enabled: false
                    }
                },
                {
                    type: 'line',
                    name: 'Rolling average',
                    color: '#e07c04',
                    zIndex: 9,
                    data: JSON.parse(widget.attr('data-cc-rolling')),
                    marker: {
                        enabled: false
                    },
                    enableMouseTracking: false
                },
                {
                    type: 'arearange',
                    name: 'Standard deviation',
                    data: JSON.parse(widget.attr('data-cc-standard-deviation')),
                    color: '#F3F4F6',
                    marker: {
                        enabled: false
                    },
                    zIndex: 7,
                    enableMouseTracking: false
                }
            ];

            $('#container-cc').highcharts({
                chart: chartype,
                xAxis: chartxaxis,
                yAxis: chartyaxis,
                legend: chartlegend,
                plotOptions: chartplotoptions,
                series: chartseries,
                credits: {
                    enabled: false
                },
                title: {
                    text: ''
                },
                exporting: {
                    enabled: false
                }
            });
        }
    }

    function initNotifications() {
        $('.remove').unbind().on('click', function () {
            let popup = $(this).parent();

            popup.addClass('opacity-0 translate-x-4');

            setTimeout(function () {
                popup.remove();
            }, 450)
        });

        $('.notification').each(function () {
            let popup = $(this);

            setTimeout(function () {
                popup.addClass('opacity-0 translate-x-4');

                setTimeout(function () {
                    popup.remove();
                }, 450);
            }, 8000);
        })
    }

    $(document).ready(initOnce);
    $(document).render(init);
}(jQuery));

/*
 * Socket.io wrapper functions
 */
function ticketUpdate(element, partial, ticket, notification = false) {
    $(document).trigger('socketEmit', ['ticketUpdate', {element: element, partial: partial, ticket: ticket, notification: notification}]);
}

function checklistUpdate(element, partial, checklist) {
    $(document).trigger('socketEmit', ['checklistUpdate', {element: element, partial: partial, checklist: checklist}]);
}

function sectionUpdate(element, partial, section, notification = false) {
    $(document).trigger('socketEmit', ['ticketAdd', {element: element, partial: partial, section: section, notification: notification}]);
}

function flowUpdate(project, flow) {
    $(document).trigger('socketEmit', ['flowUpdate', {project: project, flow: flow}]);
}

function projectUpdate(element, partial, project) {
    $(document).trigger('socketEmit', ['flowUpdate', {element: element, partial: partial, project: project}]);
}

/*
 * Helper functions
 */
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
                
                let sectionHeader = $(this).children('.section-header').first();
                let sectionBody = $(this).children('.section-body').first();

                subsection.id = $(this).attr('data-id');
                subsection.title = sectionHeader.children('input').first().val().trim();
                subsection.wipLimit = sectionBody.find('input[type="text"]').val().trim();
                subsection.markComplete = sectionBody.find('input[type="radio"]').is(':checked');

                subsections.push(subsection);
            });

            section.subsections = subsections;
        } else {
            let sectionHeader = $(this).children('.section-header').first();
            let sectionBody = $(this).children('.section-body').first();
            
            section.id = $(item).attr('data-id');
            section.title = sectionHeader.children('input').first().val().trim();
            section.wipLimit = sectionBody.find('input[type="text"]').val().trim();
            section.markComplete = sectionBody.find('input[type="radio"]').is(':checked');
            section.subsections = [];
        }

        sections[index] = section;
    });

    return JSON.stringify(sections);
}
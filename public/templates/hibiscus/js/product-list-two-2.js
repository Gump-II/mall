layui.define(['laypage', 'layer', 'jquery', 'laytpl', 'element', 'flow'], function(exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var laytpl = layui.laytpl;
    var element = layui.element;
    var flow = layui.flow;
    var total_page = 2;
    var total_count = 0;
    var device = layui.device();
    var laypage = layui.laypage;
    var reg = /<[^<>]+>/g;
    var type = "2";
    var search = "";
    var map = {
        '1': '电子资料',
        '2': '设计仿真',
        '3': '硬件电路',
    }

    $(".notice").on("click", function(event) {
        layer.open({
            type: 1,
            title: false, //不显示标题栏
            closeBtn: true,
            area: '300px;',
            shade: 0.8,
            id: 'LAY_layuipro', //设定一个id，防止重复弹出
            btn: ['我已知晓(￣▽￣)"'],
            btnAlign: 'c',
            moveType: 1, //拖拽模式，0或者1
            content: ' <div style="padding: 20px; line-height: 22px; background-color: #393D49; color: #fff; font-weight: 300;">公告： <br> <br> 由于近期对设计作品发布模块进行维护， 所以暂停开放发布模块功能， 请各位设计大佬静候此模块功能更新上线<br> <br> 汇智园为作品商家和设计需求提供最好的服务  <br> <br> 谢谢您的支持^ _ ^！  </div>',
        });
    })

    function getProduct(p, val, type) {
        var limit = 24;
        var i = layer.load(2, { shade: [0.5, '#fff'] });
        $.ajax({
            url: '/product/get/?limit=' + limit + '&page=' + p + '&search=' + val + '&tid=' + type,
            type: 'POST',
            dataType: 'json',
            //data: { "tid": 0 },
        }).done(function(res) {
            if (res.code == '0') {
                var getTpl = null;
                var goodsdetail;
                if (device.weixin || device.android || device.ios) {
                    getTpl = product_list_two_mobile_tpl.innerHTML;
                    $("#product-list-two-view").attr("class", "layui-row layui-col-space10");
                    goodsdetail = document.getElementsByClassName('layui-col-space10');
                } else {
                    getTpl = product_list_two_tpl.innerHTML;
                    $("#product-list-two-view").attr("class", "layui-row product-list-two-view");
                    goodsdetail = document.getElementsByClassName('product-list-two-view');
                }
                laytpl(getTpl).render(res, function(html) {
                    // 获取 goodsdetail标签下的所有子节点
                    var pObjs = goodsdetail[0].childNodes;
                    for (var i = pObjs.length - 1; i >= 0; i--) { // 一定要倒序，正序是删不干净的，可自行尝试
                        goodsdetail[0].removeChild(pObjs[i]);
                    }
                    $("#product-list-two-view").append(html);
                });
                element.render('product-list-two-view');
                //total_count = Math.ceil(parseInt(res.count * 10) / limit);
                //total_count = res.count;


                //获取msg的内容，将里面的标签替换掉
                var msgContent = document.getElementsByClassName('msg');
                var commodityType = document.getElementsByClassName('commodity-type');
                var length;
                if (device.weixin || device.android || device.ios) {
                    length = commodityType.length
                    for (var j = 0; j < length; j++) {
                        commodityType[j].innerHTML = map[commodityType[j].innerHTML]
                    }
                } else {
                    length = msgContent.length;
                    for (var j = 0; j < length; j++) {
                        msgContent[j].innerHTML = msgContent[j].innerHTML.replace(/(&lt;)/g, "<").replace(/(&gt;)/g, '>').replace(reg, '');
                        commodityType[j].innerHTML = map[commodityType[j].innerHTML]
                    }
                }
            } else {
                layer.msg(res.msg, { icon: 2, time: 5000 });
            }
        }).fail(function() {
            layer.msg('服务器连接失败，请联系管理员', { icon: 2, time: 5000 });
        }).always(function() {
            layer.close(i);
        });
    };
    //异步转换成同步，获取总页数，刷新分页插件
    $.ajaxSettings.async = false;
    $.ajax({
        url: '/product/get/?limit=24&page=1&search=&tid=2',
        type: 'POST',
        dataType: 'json',
    }).done(function(res) {
        if (res.code == '0') {
            //渲染数据
            total_count = res.count;
        }
    });
    $.ajaxSettings.async = true;

    //首页广告弹窗
    var layerad = $("#layerad").html();
    if (typeof(layerad) != "undefined") {
        if (layerad.length > 0) {
            layer.open({
                type: 1,
                title: false,
                closeBtn: false,
                area: '300px;',
                shade: 0.8,
                id: 'zlkbAD',
                btn: ['关闭'],
                btnAlign: 'c',
                moveType: 1, //拖拽模式，0或者1
                content: '<div style="padding: 50px; line-height: 22px; background-color: #393D49; color: #fff; font-weight: 300;">' + layerad + '</div>'
            });
        }
    }

    laypage.render({
        elem: 'pager',
        count: total_count, //数据总数
        limit: 20,
        jump: function(obj) {
             getProduct(obj.curr, search, type);
            // if(search === ""){
            //      getProduct(obj.curr, search, type);
            // }else{
            //      getProduct(obj.curr, search, "");
            // }
        }
    });
    //选项卡切换
    //触发事件
    var active = {
        reload: function() {
            //切换到指定Tab项
            element.tabChange('tab-all', '1'); //切换到：用户管理
        }
    };
    element.on('tab(tab-all)', function(elem) {
        search = ''
        type = $(this).attr('data-status');
        getProduct(1, search, type);
    });
    $(document).on('click', "#search", function() {
        search = document.getElementById('demoReload').value;
        //切换到所有选项
        //element.tabChange('tab-all', '1');
        getProduct(1, search, "");
        //getProduct(1, search, "2");
    });

    // //流媒体
    // flow.load({
    //     elem: '#more',
    //     done: function(page, next) {
    //         getProduct(page);
    //         next('', page < total_page);
    //     }
    // });

    exports('product-list-two', null)
});
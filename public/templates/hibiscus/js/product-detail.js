layui.define(['laypage', 'layer', 'jquery', 'laytpl', 'element', 'flow'], function(exports) {
    let $ = layui.jquery;
    let layer = layui.layer;
    let laytpl = layui.laytpl;
    let element = layui.element;
    let total_count = 900;
    let reg = /<[^<>]+>/g;
    let device = layui.device();
    let search = "";
    let page = 1;
    let map = {
        '1': '电子资料',
        '2': '电路设计',
        '3': '硬件电路',
    }

    function getProduct(p, val, type) {
        let limit = 6;
        let i = layer.load(2, { shade: [0.5, '#fff'] });
        $.ajax({
            url: '/product/get/?limit=' + limit + '&page=' + p + '&search=' + val + '&tid=' + type,
            type: 'POST',
            dataType: 'json',
        }).done(function(res) {
            if (res.code == '0') {
                let getTpl = null;
                let goodsdetail;
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
                    let pObjs = goodsdetail[0].childNodes;
                    for (let i = pObjs.length - 1; i >= 0; i--) { // 一定要倒序，正序是删不干净的，可自行尝试
                        goodsdetail[0].removeChild(pObjs[i]);
                    }
                    $("#product-list-two-view").append(html);
                });
                element.render('product-list-two-view');

                //获取msg的内容，将里面的标签替换掉
                let msgContent = document.getElementsByClassName('msg');
                let commodityType = document.getElementsByClassName('commodity-type');
                let length;
                if (device.weixin || device.android || device.ios) {
                    length = commodityType.length
                    for (let j = 0; j < length; j++) {
                        commodityType[j].innerHTML = map[commodityType[j].innerHTML]
                    }
                } else {
                    length = msgContent.length;
                    for (let j = 0; j < length; j++) {
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

    //右翻页
    $(document).on('click', "#right", function() {
        page += 1
        if (page * 5 >= total_count) page = 1
        judgeButton()
        getProduct(page, search, "");
    });
    //左翻页
    $(document).on('click', "#left", function() {
        page -= 1
        if (page <= 1) page = 1
        judgeButton()
        getProduct(page, search, "");
    });

    function judgeButton() {
        if (page * 5 >= total_count) {
            $('#right').attr("disabled", true);
        } else {
            $('#right').attr("disabled", false);
        }
        if (page <= 1) {
            $('#left').attr("disabled", true);
        } else {
            $('#left').attr("disabled", false);
        }
    }
    //初始化第一页
    getProduct(1, search, "");
    judgeButton();
    exports('product-detail', null)
});
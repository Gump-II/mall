<?php
/**
 * File: notify.php
 * Functionality: 支付返回处理
 * Author: 资料空白
 * Date: 2018-6-8
 */
namespace Pay;

class notify
{
	//处理返回
	public function run(array $params)
	{
		//支付渠道
		$paymethod = $params['paymethod'];
		//订单号
		$tradeid = $params['tradeid'];
		//支付金额
		$paymoney = $params['paymoney'];
		//本站订单号
		$orderid = $params['orderid'];
		
		$m_order =  \Helper::load('order');
		$m_products_card = \Helper::load('products_card');
		$m_email_queue = \Helper::load('email_queue');
		$m_products = \Helper::load('products');
		$m_config = \Helper::load('config');
		$web_config = $m_config->getConfig();
		
		try{
			//1. 通过orderid,查询order订单
			$order = $m_order->Where(array('orderid'=>$orderid))->SelectOne();
			if(!empty($order)){
				if($order['status']>0){
					$data =array('code'=>1,'msg'=>'订单已处理,请勿重复推送');
					return $data;
				}else{
					if($paymoney < $order['money']){
						//原本检测支付金额是否与订单金额一致,但由于码支付这样的收款模式导致支付金额有时会与订单不一样,所以这里进行小于判断;
						//所以,在这里如果存在类似码支付这样的第三方支付辅助工具时,有变动金额时,一定要做递增不能递减
						$data =array('code'=>1005,'msg'=>'支付金额小于订单金额');
						return $data;
					}
					
					//2.先更新支付总金额
					$update = array('status'=>1,'paytime'=>time(),'tradeid'=>$tradeid,'paymethod'=>$paymethod,'paymoney'=>$paymoney);
					$u = $m_order->Where(array('orderid'=>$orderid,'status'=>0))->Update($update);
					if(!$u){
						$data =array('code'=>1004,'msg'=>'更新失败');
						return $data;
					}else{ 
						//3.开始进行订单处理
						$product = $m_products->SelectByID('auto,stockcontrol,qty',$order['pid']);
						if(!empty($product)){
							if($product['auto']>0){
								//3.自动处理
								//查询通过订单中记录的pid，根据购买数量查询密码,修复
								if($product['stockcontrol']>0){
									$Limit = $order['number'];
								}else{
									$Limit = 1;
								}
								$cards = $m_products_card->Where(array('pid'=>$order['pid'],'active'=>0,'isdelete'=>0))->Limit($Limit)->Select();
								if(is_array($cards) AND !empty($cards) AND count($cards)==$Limit){
									//3.1 库存充足,获取对应的卡id,密码
									$card_mi_array = array_column($cards, 'card');
									$card_mi_str = implode(',',$card_mi_array);
									$card_id_array = array_column($cards, 'id');
									$card_id_str = implode(',',$card_id_array);
									//数据库对斜杆处理了一下
									$card_mi_str_get = str_replace("\\\\\\\\","\\",$card_mi_str);
									//邮件html显示处理
									$card_mi_str_get = str_replace("&","&amp;",$card_mi_str_get);
									$card_mi_str_get = str_replace("<","&lt;",$card_mi_str_get);
									$card_mi_str_get = str_replace(">","&gt;",$card_mi_str_get);
									$card_mi_str_get = str_replace("\"","&quot;",$card_mi_str_get);
									
									//字符串替换
									file_put_contents(YEWU_FILE, $card_mi_str_get, FILE_APPEND);
                                                                                                                                         
									//3.1.2 进行密码处理,如果进行了库存控制，就开始处理
									if($product['stockcontrol']>0){
										//3.1.2.1 直接进行密码与订单的关联
										$m_products_card->Where("id in ({$card_id_str})")->Where(array('active'=>0))->Update(array('active'=>1));
										//3.1.2.2 然后进行库存清减
										$qty_m = array('qty' => 'qty-'.$order['number'],'qty_virtual' => 'qty_virtual-'.$order['number'],'qty_sell'=>'qty_sell+'.$order['number']);
										$m_products->Where(array('id'=>$order['pid'],'stockcontrol'=>1))->Update($qty_m,TRUE);
										$kucunNotic=";当前商品库存剩余:".($product['qty']-$order['number']);
									}else{
										//3.1.2.3不进行库存控制时,自动发货商品是不需要减库存，也不需要取消密码；因为这种情况下的密码是通用的；
										$qty_m = array('qty_sell'=>'qty_sell+'.$order['number']);
										$m_products->Where(array('id'=>$order['pid'],'stockcontrol'=>0))->Update($qty_m,TRUE);
										$kucunNotic="";
									}
									//3.1.3 更新订单状态,同时把密码写到订单中
									$m_order->Where(array('orderid'=>$orderid,'status'=>1))->Update(array('status'=>2,'kami'=>$card_mi_str));
									//3.1.4 把邮件通知写到消息队列中，然后用定时任务去执行即可
									$m = array();
									//3.1.4.1通知用户,定时任务去执行
									if(isset($web_config['emailswitch']) AND $web_config['emailswitch']>0){
										if(isEmail($order['email'])){
											//$content ='<div style="line-height=18px;font-size:18px">=======================&gt; 汇智园电子设计 &lt;========================<br>';
											//$content .= '尊敬的顾客您好：<br>用户名：' . $order['email'] . '<br>您购买的商品：['.$order['productname'].']';
											//$content .= '<br>资料下载地址：<a href="https://pan.baidu.com/s/1bMnNEv0cCAdTZ4xYCa3rtQ" style="font-weight:bold"><span style="color:red">https://pan.baidu.com/s/1bMnNEv0cCAdTZ4xYCa3rtQ</span></a>';
											//$content .= '<br>资料解压密码：<span style="font-weight:bold;">'.$card_mi_str_get.'</span>';
											//$content .= '<br>提示：百度网盘的的提取码是<span style="color:red">hj9e</span>，每个设计有一个唯一编号，通过编号下载对应的资料压缩包到本地，通过解压密码打开压缩包即可。';
											//$content .='<br>=======================&gt; 汇智园电子设计 &lt;========================</div>';
											$content = '<meta charset="utf-8"><table width="100%"><tr><td style="width: 100%;"><center><table class="content-wrap" style="margin: 0px auto; width: 600px;"><tr><td style="margin: 0px auto; overflow: hidden; padding: 0px; border: 0px dotted rgb(238, 238, 238);"><!----><div tindex="1" style="margin: 0px auto; max-width: 600px;"><table align="center" border="0" cellpadding="0" cellspacing="0" style="background-color: rgb(88, 88, 219); background-image: url(&quot;&quot;); background-repeat: no-repeat; background-size: 100px; background-position: 1% 50%;"><tbody><tr><td style="direction: ltr; font-size: 0px; text-align: center; vertical-align: top; width: 600px;"><table width="100%" border="0" cellpadding="0" cellspacing="0" style="vertical-align: top;"><tbody><tr><td class="fourColumn column1" style="width: 50%; max-width: 50%; min-height: 1px; font-size: 13px; text-align: left; direction: ltr; vertical-align: top; padding: 0px;"><div class="full" style="margin: 0px auto; max-width: 600px;"><table align="center" border="0" cellpadding="0" cellspacing="0" class="fullTable" style="width: 300px;"><tbody><tr><td class="fullTd" style="direction: ltr; width: 300px; font-size: 0px; padding-bottom: 0px; text-align: center; vertical-align: top;"><div style="display: inline-block; vertical-align: top; width: 100%;"><table border="0" cellpadding="0" cellspacing="0" width="100%" style="vertical-align: top;"><tr><td style="font-size: 0px; word-break: break-word; width: 280px; text-align: center; padding: 10px;"><div><a href="https://www.aiesst.cn" style="font-size: 0px;"><img height="auto" alt="汇智园电子工作室" width="280" src="https://www.aiesst.cn/res/images/logo.png" style="box-sizing: border-box; border: 0px; display: inline-block; outline: none; text-decoration: none; height: auto; max-width: 100%; padding: 0px;"></a></div></td></tr></table></div></td></tr></tbody></table></div></td><td class="fourColumn column2" style="width: 50%; max-width: 50%; min-height: 1px; font-size: 13px; text-align: left; direction: ltr; vertical-align: top; padding: 0px;"><div class="full" style="margin: 0px auto; max-width: 600px;"><table align="center" border="0" cellpadding="0" cellspacing="0" class="fullTable" style="width: 300px;"><tbody><tr><td class="fullTd" style="direction: ltr; width: 300px; font-size: 0px; padding-bottom: 0px; text-align: center; vertical-align: top; background-image: url(&quot;&quot;); background-repeat: no-repeat; background-size: 100px; background-position: 10% 50%;"><table border="0" cellpadding="0" cellspacing="0" width="100%" style="vertical-align: top;"><tr><td align="left" style="font-size: 0px; padding: 52px 5px;"><div class="text" style="font-family: 微软雅黑, &quot;Microsoft YaHei&quot;; overflow-wrap: break-word; margin: 0px; text-align: right; line-height: 20px; color: rgb(255, 255, 255); font-size: 20px; font-weight: normal;"><div><p style="text-align: left; text-size-adjust: none; word-break: break-word; line-height: 20px; font-size: 20px; margin: 0px;">CourseMall</p><p style="text-align: left; text-size-adjust: none; word-break: break-word; line-height: 20px; font-size: 20px; margin: 0px;">汇智园电子商城</p></div></div></td></tr></table></td></tr></tbody></table></div></td></tr></tbody></table></td></tr></tbody></table></div><div class="full" tindex="2" style="margin: 0px auto; max-width: 600px;"><table align="center" border="0" cellpadding="0" cellspacing="0" class="fullTable" style="width: 600px;"><tbody><tr><td class="fullTd" style="direction: ltr; width: 600px; font-size: 0px; padding-bottom: 0px; text-align: center; vertical-align: top; background-image: url(&quot;&quot;); background-repeat: no-repeat; background-size: 100px; background-position: 10% 50%;"><table border="0" cellpadding="0" cellspacing="0" width="100%" style="vertical-align: top;"><tr><td align="left" style="font-size: 0px; padding: 20px;"><div class="text" style="font-family: 微软雅黑, &quot;Microsoft YaHei&quot;; overflow-wrap: break-word; margin: 0px; text-align: left; line-height: 20px; color: rgb(102, 102, 102); font-size: 14px; font-weight: normal;"><div><p style="text-size-adjust: none; word-break: break-word; line-height: 20px; font-size: 14px; margin: 0px;">您好，</p><p style="text-size-adjust: none; word-break: break-word; line-height: 20px; font-size: 14px; margin: 0px;">&nbsp;</p><p style="text-size-adjust: none; word-break: break-word; line-height: 20px; font-size: 14px; margin: 0px;"><span style="font-size: 16px;"><span style="font-family: Helvetica, "Microsoft Yahei", verdana;">用户名：</span><a style="color: rgb(29, 108, 163); font-family: Helvetica, &quot;Microsoft Yahei&quot;, verdana; text-decoration: none; font-weight: normal;"';
											$content .= $order['email'];
											$content .= ' data-auto-link="1">';
											$content .= $order['email'] ;
											$content .= '</a></span><br style="font-family: Helvetica, "Microsoft Yahei", verdana; font-size: 18px;"><span style="font-family: Helvetica, "Microsoft Yahei", verdana; font-size: 16px;">您购买的商品：';
											$content .= $order['productname'];
											$content .= '</span><br style="font-family: Helvetica, "Microsoft Yahei", verdana; font-size: 18px;"><span style="font-size: 16px;"><span style="font-family: Helvetica, "Microsoft Yahei", verdana;">资料下载地址：</span><a style="color: rgb(29, 108, 163); font-family: Helvetica, &quot;Microsoft Yahei&quot;, verdana; font-weight: normal; text-decoration: none;" href="https://pan.baidu.com/s/1bMnNEv0cCAdTZ4xYCa3rtQ"><span style="color: red;">https://pan.baidu.com/s/1bMnNEv0cCAdTZ4xYCa3rtQ</span></a></span><br style="font-family: Helvetica, "Microsoft Yahei", verdana; font-size: 18px;"><span style="font-size: 16px;"><span style="font-family: Helvetica, "Microsoft Yahei", verdana;">资料解压密码：</span><span style="font-family: Helvetica, "Microsoft Yahei", verdana; font-weight: bold;">';
											$content .= $card_mi_str_get;
											$content .= '</span></span></p><p style="text-size-adjust: none; word-break: break-word; line-height: 20px; font-size: 14px; margin: 0px;"><br style="font-family: Helvetica, "Microsoft Yahei", verdana; font-size: 18px;"><span style="font-size: 14px;"><span style="font-family: Helvetica, "Microsoft Yahei", verdana;">提示：百度网盘的的提取码是</span><strong><span style="font-family: Helvetica, "Microsoft Yahei", verdana; color: red;">hj9e</span></strong><span style="font-family: Helvetica, "Microsoft Yahei", verdana;">，每个设计有一个唯一编号，通过编号下载对应的资料压缩包到本地，通过解压密码打开压缩包即可。</span></span></p><p style="text-size-adjust: none; word-break: break-word; line-height: 20px; font-size: 14px; margin: 0px;">&nbsp;</p><p style="text-size-adjust: none; word-break: break-word; line-height: 20px; font-size: 14px; margin: 0px;">若资料解压未成功，请联系邮箱发送设计编号注明问题<span style="color: #3598db;">aiesst@163.com</span></p><p style="text-size-adjust: none; word-break: break-word; line-height: 20px; font-size: 14px; margin: 0px;">&nbsp;</p><p style="text-size-adjust: none; word-break: break-word; line-height: 20px; font-size: 14px; margin: 0px;"><span style="font-size: 12px;">汇智园电子商城</span></p></div></div></td></tr></table></td></tr></tbody></table></div><div tindex="3" style="margin: 0px auto; max-width: 600px;"><table align="center" border="0" cellpadding="0" cellspacing="0" style="background-color: rgb(210, 210, 210); background-image: url(&quot;&quot;); background-repeat: no-repeat; background-size: 100px; background-position: 1% 50%;"><tbody><tr><td style="direction: ltr; font-size: 0px; text-align: center; vertical-align: top; width: 600px;"><table width="100%" border="0" cellpadding="0" cellspacing="0" style="vertical-align: top;"><tbody><tr><td class="oneColumn column1" style="width: 100%; max-width: 100%; min-height: 1px; font-size: 13px; text-align: left; direction: ltr; vertical-align: top; padding: 0px;"><div class="full" style="margin: 0px auto; max-width: 600px;"><table align="center" border="0" cellpadding="0" cellspacing="0" class="fullTable" style="width: 600px;"><tbody><tr><td class="fullTd" style="direction: ltr; width: 600px; font-size: 0px; padding-bottom: 0px; text-align: center; vertical-align: top; background-image: url(&quot;&quot;); background-repeat: no-repeat; background-size: 100px; background-position: 10% 50%;"><table border="0" cellpadding="0" cellspacing="0" width="100%" style="vertical-align: top;"><tr><td align="left" style="font-size: 0px; padding: 20px;"><div class="text" style="font-family: 微软雅黑, &quot;Microsoft YaHei&quot;; overflow-wrap: break-word; margin: 0px; text-align: center; line-height: 20px; color: rgb(102, 102, 102); font-size: 14px; font-weight: normal;"><div><p style="text-size-adjust: none; word-break: break-word; line-height: 20px; font-size: 14px; margin: 0px;">© 2018-2020 Coursemall All Rights Reserved</p><p style="text-size-adjust: none; word-break: break-word; line-height: 20px; font-size: 14px; margin: 0px;">官方网站：<a href="www.aiesst.cn" style="text-decoration: underline; font-weight: normal;">www.aiesst.cn</a></p></div></div></td></tr></table></td></tr></tbody></table></div></td></tr></tbody></table></td></tr></tbody></table></div><div class="full" tindex="4" style="margin: 0px auto; line-height: 0px; max-width: 600px;"><table align="center" border="0" cellpadding="0" cellspacing="0" class="fullTable" style="width: 600px;"><tbody><tr><td align="center" class="fullTd" style="direction: ltr; font-size: 0px; padding: 20px; text-align: center; vertical-align: top; word-break: break-word; width: 600px; background-color: rgb(88, 88, 219); background-image: url(&quot;&quot;); background-repeat: no-repeat; background-size: 100px; background-position: 10% 50%;"><table align="center" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border-spacing: 0px;"><tbody><tr><td style="width: 375px; border-top: 1px solid rgb(204, 204, 204);"></td></tr></tbody></table></td></tr></tbody></table></div></td></tr></table></center></td></tr></table><!----><center style="text-align:center;font-size: 12px;margin:5px;color:rgb(102, 102, 102);transform: scale(.9);-webkit-transform: scale(.9);"></center>';
											$m[]=array('email'=>$order['email'],'subject'=>'商品购买成功','content'=>$content,'addtime'=>time(),'status'=>0);
										}	
									}
									//3.1.4.2通知管理员,定时任务去执行
									if(isEmail($web_config['adminemail'])){
										$content = '用户:' . $order['email'] . ',购买的商品['.$order['productname'].'],价格['.$order['price'].']密码发送成功'.$kucunNotic;
										$m[]=array('email'=>$web_config['adminemail'],'subject'=>'用户购买商品','content'=>$content,'addtime'=>time(),'status'=>0);
									}
									
									if(!empty($m)){
										$m_email_queue->MultiInsert($m);
										if($web_config['emailsendtypeswitch']>0){
											$send_email = new \Sendemail();
											$send_email->send($m);
										}
									}
									$data =array('code'=>1,'msg'=>'自动发卡');
								}else{
									//3.2 这里说明库存不足了，干脆就什么都不处理，直接记录异常，同时更新订单状态
									$m_order->Where(array('orderid'=>$orderid,'status'=>1))->Update(array('status'=>3));
									file_put_contents(YEWU_FILE, CUR_DATETIME.'-'.'库存不足，无法处理'.PHP_EOL, FILE_APPEND);
									//3.2.3邮件通知写到消息队列中，然后用定时任务去执行即可
									$m = array();
									//3.2.3.1通知用户,定时任务去执行
									if(isset($web_config['emailswitch']) AND $web_config['emailswitch']>0){
										if(isEmail($order['email'])){
											$content = '用户:' . $order['email'] . ',购买的商品['.$order['productname'].'],由于库存不足暂时无法处理,管理员正在拼命处理中....请耐心等待!';
											$m[] = array('email'=>$order['email'],'subject'=>'商品购买成功','content'=>$content,'addtime'=>time(),'status'=>0);
										}
									}
									//3.2.3.2通知管理员,定时任务去执行
									if(isEmail($web_config['adminemail'])){
										$content = '用户:' . $order['email'] . ',购买的商品['.$order['productname'].'],由于库存不足暂时无法处理,请尽快处理!';
										$m[] = array('email'=>$web_config['adminemail'],'subject'=>'用户购买商品','content'=>$content,'addtime'=>time(),'status'=>0);
									}
									
									if(!empty($m)){
										$m_email_queue->MultiInsert($m);
										if($web_config['emailsendtypeswitch']>0){
											$send_email = new \Sendemail();
											$send_email->send($m);
										}
									}
									$data =array('code'=>1,'msg'=>'库存不足,无法处理');
								}
							}else{
								//4.手工操作
								//4.1如果商品有进行库存控制，就减库存
								if($product['stockcontrol']>0){
									$qty_m = array('qty' => 'qty-'.$order['number'],'qty_virtual' => 'qty_virtual-'.$order['number'],'qty_sell'=>'qty_sell+'.$order['number']);
									$m_products->Where(array('id'=>$order['pid'],'stockcontrol'=>1))->Update($qty_m,TRUE);
								}else{
									$qty_m = array('qty_sell'=>'qty_sell+'.$order['number']);
									$m_products->Where(array('id'=>$order['pid'],'stockcontrol'=>0))->Update($qty_m,TRUE);
								}
								//4.2邮件通知写到消息队列中，然后用定时任务去执行即可
								$m = array();
								//4.2.1通知用户,定时任务去执行
								if(isset($web_config['emailswitch']) AND $web_config['emailswitch']>0){
									if(isEmail($order['email'])){
										$content = '用户:' . $order['email'] . ',购买的商品['.$order['productname'].'],属于手工发货类型，管理员即将联系您....请耐心等待!';
										$m[] = array('email'=>$order['email'],'subject'=>'商品购买成功','content'=>$content,'addtime'=>time(),'status'=>0);
									}
								}
								//4.2.2通知管理员,定时任务去执行
								if(isEmail($web_config['adminemail'])){
									$content = '用户:' . $order['email'] . ',购买的商品['.$order['productname'].'],属于手工发货类型，请尽快联系他!';
									if($order['addons']){
										$content .='订单附加信息：'.$order['addons'];
									}
									$m[] = array('email'=>$web_config['adminemail'],'subject'=>'用户购买商品','content'=>$content,'addtime'=>time(),'status'=>0);
								}
								if(!empty($m)){
									$m_email_queue->MultiInsert($m);
									if($web_config['emailsendtypeswitch']>0){
										$send_email = new \Sendemail();
										$send_email->send($m);
									}
								}
								$data =array('code'=>1,'msg'=>'手工订单');
							}
						}else{
							$data =array('code'=>1003,'msg'=>'订单对应商品不存在');
						}
					}
				}
			}else{
				$data =array('code'=>1003,'msg'=>'订单号不存在');
			}
		} catch(\Exception $e) {
			file_put_contents(YEWU_FILE, CUR_DATETIME.'-reuslt:-notify'.$e->getMessage().PHP_EOL, FILE_APPEND);
			$data =array('code'=>1001,'msg'=>$e->getMessage());
		}
		return $data;
	}
}
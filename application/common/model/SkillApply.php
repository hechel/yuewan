<?php

namespace app\common\model;

use Monolog\Handler\IFTTTHandler;
use think\Db;
use think\Model;

class SkillApply extends Model
{
    public function getList($where = []){
        return $this->alias('a')
            ->join('skill s','s.skill_id = a.skill_id','LEFT')
            ->join('users u','u.user_id = a.user_id','LEFT')
            ->where($where)
            ->field('a.*,s.skill_name,s.img as skill_img,u.nick_name,u.header_img')
            ->order('a.apply_id desc')
            ->paginate('',false,['query'=>request()->param()]);
    }

    public function getDetail($where = [])
    {
        return $this->alias('a')
            ->join('skill s','s.skill_id = a.skill_id','LEFT')
            ->join('users u','u.user_id = a.user_id','LEFT')
            ->where($where)
            ->field('a.*,s.skill_name,s.img as skill_img,u.nick_name,u.header_img')
            ->find();
    }


    /**
     * Created by xiaosong
     * E-mail:4155433@gmail.com
     * 技能详情页
     *
     */
    public function detail($map = [])
    {

        $field = 'a.my_grade,a.apply_id,a.skill_id,a.user_id,a.img,a.voice,a.video,s.skill_name,u.nick_name,u.header_img,e.online_time,e.online_status';

        $data = $this->alias('a')
            ->join('skill s','s.skill_id = a.skill_id','LEFT')
            ->join('users u','u.user_id = a.user_id','LEFT')
            ->join('user_extend e','e.user_id = a.user_id','LEFT')
            ->where($map)
            ->field($field)
            ->find();

        if ($data['online_status'] == 1){
            $data['status'] = '当前在线';
        }else{
            $data['status'] = formatTime($data['online_time']);
        }


//
//        $form['a.skill_id'] = $data['skill_id'];
//        $form['a.user_id']  = $data['user_id'];
//        $form['a.status']   = 1;
//        $form['s.status']   = 1;
//
//        $data['forms'] = Db::name('skill_form_user')->alias('a')
//            ->join('skill_form s','s.form_id = a.form_id','LEFT')
//            ->where($form)
//            ->order('a.num desc')
//            ->field('a.num,s.form_name')
//            ->select();
//
//        $tag['a.skill_id'] = $data['skill_id'];
//        $tag['a.user_id']  = $data['user_id'];
//        $tag['a.status']   = 1;
//        $tag['t.status']   = 1;
//
//        $data['tags'] = Db::name('skill_tag_user')->alias('a')
//            ->join('skill_tag t','t.tag = a.tag','LEFT')
//            ->where($tag)
//            ->order('a.num desc')
//            ->field('a.num,t.tag_name')
//            ->select();

//        $comment['a.to_user']  = $data['user_id'];
//        $comment['a.skill_id'] = $data['skill_id'];
//        $comment['a.status']   = 5;
//
//        $comments = Db::name('order')->alias('a')
//            ->join('users u','u.user_id = a.user_id','LEFT')
//            ->join('gift g','g.gift_id = a.gift_id','LEFT')
//            ->where($comment)
//            ->field('a.score,g.gift_name,g.thumbnail,g.img,a.num,a.tags,a.content,a.update_time,u.nick_name,u.header_img,u.user_id')
//            ->select();
//
//        foreach ($comments as $k => $v){
//
//            $tags['tag']    = ['in',$v['tags']];
//            $tags['status'] = 1;
//            $comments[$k]['tags'] = Db::name('skill_tag')->where($tags)->field('tag_name')->select();
//            $comments[$k]['user_id'] = hashid($v['user_id']);
//        }
//
//        $data['comments'] = $comments;

        $img['user_id'] = $data['user_id'];
        $img['status']  = 1;
        $img['type']    = 1;

        $data['imgs'] = Db::name('user_img')->where($img)->field('img')->select();

        $data['user_id'] = hashid($data['user_id']);

        return $data;

    }


    public function getMy($map = [])
    {
        return $this->alias('a')
            ->join('skill s','s.skill_id = a.skill_id','LEFT')
            ->join('users u','u.user_id  = a.user_id','LEFT')
            ->where($map)
            ->cache(3)
            ->field('s.skill_name,s.img,s.skill_id,a.is_use,a.num')
            ->select();
    }


    /**
     * Created by xiaosong
     * E-mail:4155433@gmail.com
     * param bool $my 为true时获取我接受礼物和形式
     */
    public function getRows($map = [],$my = false)
    {

        $rows = $this->alias('a')
            ->join('skill s','s.skill_id = a.skill_id','LEFT')
            ->where($map)
            ->field('s.skill_name,a.apply_id,a.my_form,a.my_gift_id,s.form_id,s.gift_id,s.spec')
            ->cache(15)
            ->select();
        foreach ($rows as $k => $v){

            $mini_price = Db::name('gift')->where('gift_id',$v['my_gift_id'])->value('price');
            $rows[$k]['mini_price'] = $mini_price;

            if ($my){

                $form['form_id']  = ['in',$v['my_form']];

            }else{

                $form['form_id']  = ['in',$v['form_id']];

            }

            $form['status']   = 1;
            $rows[$k]['form'] = Db::name('skill_form')->where($form)->field('form_id,form_name')->cache(10)->select();

            if ($my){

                $gift['gift_id']  = ['in',$v['gift_id']];
                $gift['price']    = ['>=',$mini_price];

            }else{

                $gift['gift_id']  = ['in',$v['gift_id']];

            }

            $gift['status']   = 1;
            $rows[$k]['gift'] = Db::name('gift')->where($gift)->field('gift_id,gift_name,thumbnail,img,price')->order('price')->cache(10)->select();

        }

        return $rows;
    }


    /**
     * Created by xiaosong
     * E-mail:4155433@gmail.com
     * 用户筛选
     */
    public function getUsers($map = [],$order = '')
    {

        $join = [
            ['user_extend e','e.user_id = a.user_id','left'],
            ['users u','u.user_id = a.user_id','left']
        ];

        $field = 'e.place,a.skill_id,a.mini_price,a.num,u.user_id,u.header_img,u.sex,u.nick_name,u.sign,e.online_time,e.online_status,e.room_id,e.noble_id,e.level,e.noble_time';

        $rows =  $this->alias('a')->where($map)->join($join)
            ->field($field)
            ->order($order)
            ->cache(15)
            ->paginate()->each(function ($item){

                $item['distance'] = 0;

                if ($item['online_status']){

                    $item['status'] = '当前在线';

                }else{

                    $item['status'] = formatTime($item['online_time']);

                }
                $item['noble_id'] = \app\api\controller\Base::checkNoble($item);



                if ($item['room_id']){

                    $item['skill']['apply_id']   = 0;
                    $item['skill']['skill_name'] = '';

                }else{
                    //用户不在房间中   查询是否有技能
                    $skill['a.skill_id'] = $item['skill_id'];
                    $skill['a.user_id']  = $item['user_id'];

                    $item['skill'] = Db::name('skill_apply')
                        ->alias('a')
                        ->join( [

                            ['skill s','s.skill_id = a.skill_id','left']

                        ])
                        ->where($skill)
                        ->field('a.apply_id,s.skill_name')
                        ->find();

                }

                $item['user_id'] = hashid($item['user_id']);

            });

        return ['thisPage'=>$rows->currentPage(),'hasNext'=>$rows->hasMore(),'data'=>$rows->items()];
    }


    /**
     * Created by xiaosong
     * E-mail:4155433@gmail.com
     * 同城查询
     */
    public function getCity($map = [],$userExtra = [] ,$distance = 5,$max = 50)
    {

        $join = [
            ['user_extend e','e.user_id = a.user_id','left'],
            ['users u','u.user_id = a.user_id','left']
        ];

        $log = $userExtra['log'];
        $lat = $userExtra['lat'];

        $field = "e.place,(st_distance (point (e.log,e.lat),point($log,$lat) ) / 0.0111) AS distance,a.skill_id,a.mini_price,a.num,u.user_id,u.header_img,u.sex,u.nick_name,u.sign,e.online_time,e.online_status,e.room_id,e.noble_id,e.noble_time,e.level";

        $rows =  $this->alias('a')->where($map)->join($join)
            ->field($field)
            ->order('distance')
            ->having("distance < $distance")
            ->select();

        if (count($rows) <= 15 && $distance < $max){

            $distance += 10;

            return $this->getCity($map,$userExtra,$distance,$max);

        }else{

            if ($distance >= $max){
                //超过最大范围不允许继续请求
                $hasNext = false;
            }else{
                $hasNext = true;
            }

            foreach ($rows as $k => $item){

                if ($item['online_status']){

                    $rows[$k]['status'] = '当前在线';

                }else{

                    $rows[$k]['status'] = formatTime($item['online_time']);

                }
                $item['noble_id'] = \app\api\controller\Base::checkNoble($item);

                if ($item['room_id']){

                    $rows[$k]['skill']['apply_id']   = 0;
                    $rows[$k]['skill']['skill_name'] = '';

                }else{
                    //用户不在房间中   查询是否有技能
                    $skill['a.skill_id'] = $item['skill_id'];
                    $skill['a.user_id']  = $item['user_id'];

                    $rows[$k]['skill'] = Db::name('skill_apply')
                        ->alias('a')
                        ->join( [

                            ['skill s','s.skill_id = a.skill_id','left']

                        ])
                        ->where($skill)
                        ->field('a.apply_id,s.skill_name')
                        ->find();

                }

                $rows[$k]['user_id'] = hashid($item['user_id']);

            }

            return ['hasNext'=>$hasNext,'thisPage'=>$distance,'data'=>$rows];

        }

    }





    
    



}

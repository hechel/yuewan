<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/26
 * Time: 15:46
 */

namespace app\common\model;


use think\Model;

class RoomFollow extends Model
{

    public function getRows($map = [])
    {
        $join = [
            ['user_extend e','e.user_id = a.user_id','left'],
            ['users u','u.user_id = a.user_id','left']
        ];

        $field = 'a.status,u.birthday,u.user_id,u.header_img,u.sex,u.nick_name,u.tag,e.noble_id,e.noble_time,e.level';

        $rows =  $this->alias('a')
            ->where($map)
            ->join($join)
            ->field($field)
            ->cache(15)
            ->paginate()->each(function ($item){
                $item['noble_id'] = \app\api\controller\Base::checkNoble($item);
                $item['user_id']  = hashid($item['user_id']);
            });
        return ['thisPage'=>$rows->currentPage(),'hasNext'=>$rows->hasMore(),'data'=>$rows->items()];
    }

}
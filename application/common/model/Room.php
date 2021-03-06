<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/26
 * Time: 15:46
 */

namespace app\common\model;


use app\admin\controller\Play;
use app\api\controller\User;
use function Qiniu\waterImg;
use think\Db;
use think\Model;
use wheat\Wheat;

class Room extends Model
{

    protected static $room_type = null;

    protected function initialize()
    {
        parent::initialize();
        self::$room_type = Play::$room_type;
    }


    public function profile()
    {

        return $this->hasOne('users', 'user_id', 'user_id')->field('nick_name,header_img');
    }



    public function getList($where = []){
        return $this
            ->where($where)
            ->order('create_time desc')
            ->field('room_name,top,cid,room_id,img,status,create_time,update_time,type')
            ->paginate(15,false,['query'=>request()->param()]);
    }


    public function getRows($map = [])
    {

        $rows = $this->alias('a')->where($map)
//            ->join([
//                [ 'users r','r.role_id = a.role_id','LEFT'],
//            ])
            ->field('a.room_id,a.cid,a.type,a.room_name,a.img,a.is_lock')
            ->order(['top'=>'desc','room_id'=>'desc'])
            ->cache(10)
            ->paginate(15)->each(function ($item){
                $this->item($item);
            });

        return ['thisPage'=>$rows->currentPage(),'hasNext'=>$rows->hasMore(),'data'=>$rows->items()];
    }

    public function room($map = [])
    {
        $rows = $this->alias('a')->where($map)
            ->join([
                [ 'room_follow r','r.room_id = a.room_id','LEFT'],
            ])
            ->field('a.room_id,a.cid,a.type,a.room_name,a.img,a.is_lock')
            ->order(['top'=>'desc','room_id'=>'desc'])
            ->cache(10)
            ->limit(2)
            ->select();

        foreach ($rows as $k => $v){
            $rows[$k] = $this->item($v);
        }
        return $rows;

    }
    
    
    
    public function item($item)
    {
        $item['hot'] = hotValue($item['room_id']);
        if ($item['type'] == 4){
            $item['type'] = Db::name('room_category')->where('cid',$item['cid'])->cache(60)->value('cate_name');
        }else{
            $item['type'] = self::$room_type[$item['type']]['type_name'];
        }
        return $item;
    }



    public function getRows1($where = [])
    {
        $exp = new \think\db\Expression('field(a.play_status,1,2,0),a.update_time desc');
        $rows = $this->alias('a')->where($where)
            ->join([
                [ 'play_category b','b.cid = a.cid','LEFT'],
                [ 'role r','r.role_id = a.role_id','LEFT'],
            ])
            ->field('a.cid,b.cate_name,a.play_status,a.room_id,r.role_name,r.header_img,a.detail,a.img,a.room_name,a.VIP,a.official,a.fans')
            ->order($exp)
            ->cache(10)
            ->paginate();
//        var_dump( $rows);exit;
        $items = $rows->items();
        if (empty($items)) return false;
        foreach ($items as $k => $v){
            if ($v['play_status'] == 1){
                $guess['status'] = ['between','1,2'];
                $guess['room_id'] = $v['room_id'];
                $num = Db::name('guess')->where($guess)->count('guess_id');
                if ($num){
                    $items[$k]['remark'] = '竞猜';
                }else{
                    $items[$k]['remark'] = '直播中';
                }
            }elseif ($v['play_status'] == 0){
                $items[$k]['remark'] = '聊天开放中';
            }elseif ($v['play_status'] == 2){
                $items[$k]['remark'] = '直播预告';
            }
            $items[$k]['hot'] = hotValue($v['room_id']);
            $items[$k]['room_id'] = hashid($v['room_id']);
        }
        return ['thisPage'=>$rows->currentPage(),'hasNext'=>$rows->hasMore(),'data'=>$items,'lastPage'=>$rows->lastPage()];
    }
    
    
    
    
    public function detail($where = [])
    {
        return $this->alias('a')
            ->join('room_category b','b.cid = a.cid','LEFT')
            ->where($where)
            ->field('a.*,b.cate_name')->find();

    }

    public function joinRoom($room_id,$user_id = 0,$password = null)
    {
        $map['room_id'] = $room_id;
        $map['status']  = 1;

        $data = $this->where($map)->field('room_id,user_id,room_name,img,is_lock,password,notice,type')->cache(5)->find();

        if (!$data){
            api_return(0,'参数错误');
        }



        //查询是否关注房间 可用于判断权限
        $data['is_follow'] =  \app\api\controller\Base::roomPower($data['room_id'],$user_id);

        //查询用户权限
        if ($data['user_id'] == $user_id){
            $data['power'] = 1;//房主
        }elseif ($data['is_follow'] == 2){
            $data['power'] = 2;//高级管理员 |接待
        }elseif ($data['is_follow'] == 3){
            $data['power'] = 3;// 普通管理员
        }else{
            $data['power'] = 4;//普通成员
        }

        //管理员免密码进入
        if ($data['is_lock']){
            if ($data['power'] == 4){
                if (empty($password)){
                    api_return(401,'请输入密码');
                }else{
                    if ($data['password'] != $password){
                        api_return(402,'密码错误');
                    }
                }
            }
        }

        $data['self'] = hashid($user_id);

        //查询用户是否被封禁
        $ban['user_id']  = $user_id;
        $ban['status']   = 1;
        $ban['end_time'] = ['>',time()];
        $ban['room_id']  = $data['room_id'];
        $black = Db::name('room_blacklist')->where($ban)->cache(3)->value('end_time');
        if ($black){
            api_return(403,'您已被拉入黑名单',$black['end_time']);
        }

        //查询房间热度
        $data['hot'] = hotValue($data['room_id']);

        $wheat = Wheat::wheat($data['room_id'],$data['type']);

        $data['wheat'] = $wheat;

        unset($data['user_id']);

        return $data;
    }


    /**
     * 获取房主及管理员
     */
    public function getAdmins($where = [])
    {
        $data = [];
        //房主信息
        $admin = $this
            ->alias('a')
            ->where($where)
            ->join([
                ['role r','r.role_id = a.role_id','left'],
            ])
            ->field('r.role_name,r.header_img,r.role_id')
            ->cache(60)
            ->find();
//        $admin = Db::name('role')->where('role_id',$role_id)->field('role_name,header_img,role_id')->find();
        if (!empty($admin)){
            $admin['role_id'] = hashid($admin['role_id']);
            $admin['status']  = 1;
            $admin['remark']  = '房主';
        }
        $data[] = $admin;
        //管理员状态为粉丝表状态2
        $where['a.status'] = 2;
        //管理员列表
        $admins = db('room_follow')
            ->alias('a')
            ->where($where)
            ->join([
                ['role r','r.role_id = a.role_id','left'],
            ])
            ->field('r.role_name,r.header_img,r.role_id,a.status')
            ->cache(2)
            ->select();
        if (!empty($admins)){
            foreach ($admins as $k => $v) {
                $admins[$k]['role_id'] = hashid($v['role_id']);
                $admins[$k]['remark'] = '管理员';
                $data[] = $admins[$k];
            }
        }
        return $data;
    }



    /**
     * 获取房间成员
     */
    public function getUsers($where = [])
    {
        $data = [];
        //房主信息
        $admin = $this
            ->alias('a')
            ->where($where)
            ->join([
                ['role r','r.role_id = a.role_id','left'],
            ])
            ->field('r.role_name,r.header_img,r.role_id')
            ->cache(60)
            ->find();
        if (!empty($admin)){
            $admin['role_id'] = hashid($admin['role_id']);
            $admin['remark']  = '房主';
        }
        $data[] = $admin;
        //管理员状态为粉丝表状态2
        $where['a.status'] = 2;
        //管理员列表
        $admins = db('room_follow')
            ->alias('a')
            ->where($where)
            ->join([
                ['role r','r.role_id = a.role_id','left'],
            ])
            ->field('r.role_name,r.header_img,r.role_id')
            ->cache(60)
            ->select();
        if (!empty($admins)){
            foreach ($admins as $k => $v) {
                $admins[$k]['role_id'] = hashid($v['role_id']);
                $admins[$k]['remark'] = '管理员';
                $data[] = $admins[$k];
            }
        }
        return $data;
    }




}
<?php
/**
 * Created by xiaosong
 * E-mail:306027376@qq.com
 * Date: 2018/11/14
 * Time: 10:16
 */

namespace app\admin\controller;
use app\common\logic\Logic;
use app\common\logic\SkillForm;
use app\common\logic\SkillTag;
use app\common\model\Skill as model;
use app\common\model\SkillApply;
use Qiniu\Auth;
use think\Db;
use think\queue\connector\Database;

class Skill extends Base
{

    /**
     * Created by xiaosong
     * E-mail:306027376@qq.com
     * 技能列表
     */
    public function index()
    {

        $map = [];

        if (input('skill_name')) $map['skill_name'] = ['like','%'.trim(input('skill_name')).'%'];

        if(!isEmpty($_GET['type'])) $map['type'] = trim(input('type'));

        $model = new model();
        $rows  = $model->getList($map);
        $this->assign([
            'title' => '技能列表',
            'rows' => $rows,
            'pageHTML' => $rows->render(),
        ]);

        return view();

    }

    /**
     * Created by xiaosong
     * E-mail:306027376@qq.com
     * 技能编辑
     */
    public function edit()
    {
        $table = 'skill';
        $data = null;
        if (request()->isPost()){

            $data = input('post.');


            $data['gift_id'] = implode(',',$data['gift_id']);

            $model = new Logic();

            $result = $model->saveChange($table,$data,'skill');

            if ($result){

                if (is_numeric($data['id'])){
                    $id = $data['id'];
                }else{
                    $id = $model->getLastInsID();
                }


                //添加更新形式
                $rows = [];

                $add  = [];

                foreach ($data['form_name'] as $k => $v){

                    $arr = [];
                    $arr['form_name'] = $v;
                    $arr['form_id']   = $data['form_id'][$k];
                    $arr['skill_id']  = $id;
                    if ($arr['form_id']){
                        $rows[] = $arr;
                    }else{
                        $add[] = $arr;
                    }

                }

                $skill = new SkillForm();

                $map['skill_id'] = $id;

                $skill->where($map)->update(['skill_id'=>0]);

                if ($rows){

                    $skill->saveAll($rows);

                }

                if($add){

                    $skill->insertAll($add);

                }


                //添加更新标签
                $rows2 = [];

                $add2  = [];


                foreach ($data['tag_name'] as $k => $v){

                    $arr = [];
                    $arr['tag_name'] = $v;
                    $arr['tag']   = $data['tag'][$k];
                    $arr['skill_id']  = $id;
                    if ($arr['tag']){
                        $rows2[] = $arr;
                    }else{
                        $add2[] = $arr;
                    }

                }


                $tag = new SkillTag();

                $tag->where($map)->update(['skill_id'=>0]);

                if ($rows2){

                    $tag->saveAll($rows2);

                }

                if($add2){

                    $tag->insertAll($add2);

                }



                $column = $skill->where($map)->column('form_id');

                $form_id = implode(',',$column);

                $column2 = $tag->where($map)->column('tag');

                $tag = implode(',',$column2);


                $model->where($map)->update(['form_id'=>$form_id,'tag'=>$tag]);

                $this->success('操作成功',url('index'));
            }
            $this->error($model->getError());

        }

//        $this->_edit($table,'技能编辑',url('index'),'skill',$data);

        $auth  = new Auth(config('qiniu')['ACCESSKEY'], config('qiniu')['SECRETKEY']);
        $token = $auth->uploadToken(config('qiniu')['bucket']);
        $domain = config('qiniu')['domain'];

        $gift = Db::name('gift')->select();

        $this->_find($table);

        $this->assign([
            'title' => '技能编辑',
            'gift' => $gift,
            'token' => $token,
            'domain'=> $domain,
        ]);

        return view();
    }


    /**
     * Created by xiaosong
     * E-mail:306027376@qq.com
     * 更改状态
     */
    public function change()
    {
        $this->_change('skill');
    }


    /**
     * Created by xiaosong
     * E-mail:306027376@qq.com
     * 技能删除
     */
    public function del()
    {
        $this->_del('skill');
    }


    /**
     * Created by xiaosong
     * E-mail:4155433@gmail.com
     * 资质申请列表
     */
    public function apply()
    {

        $map = [];

        if (input('skill_name')) $map['skill_name'] = ['like','%'.trim(input('skill_name')).'%'];

        if(!isEmpty($_GET['type'])) $map['type'] = trim(input('type'));

        $model = new SkillApply();
        $rows  = $model->getList($map);
        $this->assign([
            'title' => '技能列表',
            'rows' => $rows,
            'pageHTML' => $rows->render(),
        ]);

        return view();
    }

    /**
     * Created by xiaosong
     * E-mail:4155433@gmail.com
     * 资质申请编辑
     */
    public function apply_edit()
    {

        if (request()->isPost()){
            $data = input('post.');

            if (!is_numeric($data['id'])){
                $this->error('参数错误');
            }
            $result = Db::name('skill_apply')->where('apply_id',$data['id'])->update(['status'=>$data['status']]);

            if ($result){
                $this->success('操作成功',url('apply'));
            }
            $this->error('操作失败');

        }


        $model = new SkillApply();

        $data = $model->getDetail(['a.apply_id'=>input('id')]);

        $this->assign([
            'title' => '技能申请编辑',
            'data' => $data,
        ]);
        return view();

    }



}
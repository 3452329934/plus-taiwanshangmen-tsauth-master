<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-7">
        <div class="panel panel-default">
          <div class="panel-heading">极光推送配置</div>
          <div class="panel-body">

            <ui-loading v-if="loading" />

            <form class="form-horizontal" v-else>
              <!-- 开关 -->
              <div class="form-group">
                <label class="col-sm-3 control-label">开关</label>
                <div class="col-sm-9">
                  <select class="form-control" v-model="form.switch">
                    <option :value="true">开启</option>
                    <option :value="false">关闭</option>
                  </select>
                  <span class="help-block">开启或者关闭极光推送，如果没有app，请关闭</span>
                </div>
              </div>

              <!-- 应用 Key -->
              <div class="form-group">
                <label class="col-sm-3 control-label">AppKey</label>
                <div class="col-sm-9">
                  <input type="text" class="form-control" v-model="form.app_key">
                  <span class="help-block">请填写创建应用后的 App Key</span>
                </div>
              </div>

              <!-- 应用密钥 -->
              <div class="form-group">
                <label class="col-sm-3 control-label">Master Secret</label>
                <div class="col-sm-9">
                  <input type="text" class="form-control" v-model="form.master_secret">
                </div>
              </div>
              <!-- 推送环境 -->
              <div class="form-group">
                <label class="col-sm-3 control-label">推送环境</label>
                <div class="col-sm-9">
                  <select class="form-control" v-model="form.apns_production">
                    <option :value="true">生产环境</option>
                    <option :value="false">测试环境</option>
                    </select>
                </div>
              </div>

              <!-- 提交按钮 -->
              <div class="form-group">
                <div class="col-sm-9 col-sm-offset-3">
                  <ui-button type="button" class="btn btn-primary" @click="onSubmit" />
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-5">
        <div class="panel panel-default">
          <div class="panel-heading">帮助</div>
          <div class="panel-body">
            极光推送用于向app推送消息，你需要去「<a target="_blank" href="https://www.jiguang.cn/">极光官网</a>」注册帐号、创建应用后将应用信息填入次页。
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { jpush } from '../../api/vendor';
  export default {
    data: () => ({
      form: {
        switch: false,
        app_key: '',
        master_secret: '',
        apns_production: false,
      },
      loading: true,
    }),
    methods: {
      onSubmit(event) {
        jpush.update(this.form).then(() => {
          this.$store.dispatch("alert-open", { type: "success", message: '提交成功' });
        }).catch(({ response: { data: message = "提交失败，请刷新页面重试！" } }) => {
          this.$store.dispatch("alert-open", { type: "danger", message });
        }).finally(event.stopProcessing);
      }
    },
    created() {
      jpush.get().then(({ data }) => {
        this.loading = false;
        this.form = data;
      }).catch(({ response: { data: message = "获取失败，请刷新页面重试！" } }) => {
        this.$store.dispatch("alert-open", { type: "danger", message });
      });
    }
  }
</script>


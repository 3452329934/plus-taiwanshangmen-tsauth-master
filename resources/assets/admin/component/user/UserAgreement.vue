<template>
  <div class="container-fluid" style="margin-top:10px;">
    <div class="panel panel-default">
      <div class="panel-heading">
        用户协议设置
      </div>
      <div class="panel-body">
        <loading :loadding="loading"/>
        <div class="form-horizontal" v-show="!loading">
          <div class="form-group">
            <label class="col-sm-3 control-label">用户协议页面地址</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" placeholder="填写其他页面，优先级高" name="url" v-model="url"/>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-3 control-label" for="rule-content">协议内容</label>
            <div class="col-sm-9">
              <mavon-editor placeholder="输入用户协议的markdown内容" v-model="content" @imgAdd="$imgAdd" ref="editor"
                            :apiHost="apiHost"/>
            </div>
          </div>
          <!-- Button -->
          <div class="form-group">
            <div class="col-sm-offset-3 col-sm-4">
              <button v-if="loading" type="button" class="btn btn-primary" disabled="disabled">
                <span class="glyphicon glyphicon-refresh component-loadding-icon"/>
              </button>
              <button v-else type="button" class="btn btn-primary" @click="saveConfig">保存设置</button>
            </div>
            <div class="col-sm-4">
              <p class="text-success">{{ message }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import request, { createAPI } from '../../util/request'
  import { mavonEditor } from '@slimkit/plus-editor'
  import '@slimkit/plus-editor/dist/css/index.css'
  import 'highlight.js/styles/github.css'
  import { uploadFile } from '../../util/upload'

  const domain = window.TS.domain || ''

  export default {
    name: 'AboutUs',
    components: {
      mavonEditor
    },
    data: () => ({
      content: '',
      apiHost: domain,
      loading: true,
      url: '',
      message: ''
    }),
    methods: {
      // 绑定@imgAdd event
      $imgAdd (pos, $file) {
        // 第一步.将图片上传到服务器.
        const formData = new FormData()
        formData.append('image', $file)
        uploadFile($file, id => {
          this.$refs.editor.$img2Url(pos, id)
        })
      },
      /**
       * 保存
       * @Author   Wayne
       * @DateTime 2018-07-04
       * @Email    qiaobin@zhiyicx.com
       * @return   {[type]}            [description]
       */
      saveConfig () {
        const { url, content, loading } = this
        let params = { url, content, type: 'user' }
        if (!loading) {
          this.loading = true
          request.patch(createAPI('site-agreement'), params, {
            validateStatus: status => status === 201
          }).then(() => {
            this.message = '操作成功'
            setTimeout(() => {
              this.message = ''
            }, 2000)
          }).finally(() => {
            this.loading = false
          })
        }
      }
    },
    created () {
      this.loading = true
      request.get(createAPI('site-agreement'), {
        params: { type: 'user' },
        validateStatus: status => status === 200
      }).then(({ data: { content, url } }) => {
        this.url = url
        this.content = content
        this.loading = false
      }).finally(() => {
        this.loading = false
      })
    }
  }
</script>

<style scoped>

</style>

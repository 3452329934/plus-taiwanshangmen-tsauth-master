<template>
  <button :label="label" :proces-lable="procesLable" @click="handleClick" :disabled="processing">
    <slot :processing="processing" :stopProcessing="stopProcessing"></slot>
  </button>
</template>

<script>
  export default {
    name: 'ui-process-button',
    props: {
      label: {type: String},
      procesLable: {type: String}
    },
    data: function() {
      return {
        processing: false
      }
    },
    methods: {
      handleClick (event) {
        if (this.processing === true) {
          return
        }
        let _this = this
        event.stopProcessing = function() {
          _this.stopProcessing()
        }

        this.processing = true
        this.$emit('click', event)
      },

      stopProcessing () {
        this.processing = false
      }
    }
  }
</script>

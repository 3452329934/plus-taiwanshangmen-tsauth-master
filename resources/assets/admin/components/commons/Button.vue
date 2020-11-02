<template>
  <ui-process-button :label="label" :proces-lable="procesLable" @click="handleClick">
    <template slot-scope="{ processing }">
      <template v-if="processing">
        <ui-loading></ui-loading>
        {{ procesLable }}
      </template>
      <template v-else>
        <slot>{{ label }}</slot>
      </template>
    </template>
  </ui-process-button>
</template>

<script>
  export default {
    name: 'ui-button',
    props: {
      label: { type: String, default: '提交' },
      procesLable: { type: String, default: '提交中...' }
    },
    methods: {
      handleClick (event) {
        this.$emit('click', event)
      }
    }
  }
</script>

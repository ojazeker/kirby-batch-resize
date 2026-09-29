<template>
  <div class="k-table clean-up-table">
    <table>
      <thead>
        <tr>
          <th v-for="column in columns" :key="column.key">{{ column.label }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, index) in rows" :key="row.id + '-' + index">
          <td v-for="column in columns" :key="column.key">{{ row[column.key] }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
export default {
  props: {
    columns: { type: Array, required: true },
    rows: { type: Array, required: true }
  }
};
</script>

<style>
.clean-up-table { overflow-x: auto; }
.clean-up-table table { width: 100%; }
.clean-up-table th, .clean-up-table td { padding: .6rem .75rem; text-align: left; vertical-align: top; }
.clean-up-table td:first-child { overflow-wrap: anywhere; }
</style>
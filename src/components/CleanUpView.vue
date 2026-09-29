<template>
  <k-panel-inside class="clean-up-view">
    <k-header>Clean Up</k-header>

    <k-box
      theme="negative"
      text="Processing overwrites original images. Back up your files before starting."
    />

    <form class="clean-up-options" @submit.prevent="refresh">
      <k-text-field
        v-model.trim="forceName"
        name="force"
        label="Fallback file blueprint"
        placeholder="image"
        :disabled="running || started"
      />
      <k-number-field
        v-model="limit"
        name="limit"
        label="Files per batch"
        min="1"
        max="100"
        required
        :disabled="running || started"
      />
      <k-button
        type="submit"
        icon="refresh"
        variant="filled"
        text="Preview"
        :disabled="loading || running || nextOffset !== null"
      />
    </form>

    <k-box v-if="loading" theme="info" text="Scanning images..." />

    <template v-if="pending !== null && !loading">
      <section v-if="!started" class="clean-up-section">
        <k-headline>Pending ({{ pending.length }})</k-headline>
        <template v-if="pending.length">
          <k-button
            icon="play"
            theme="red"
            variant="filled"
            :disabled="running || !previewIsCurrent"
            :text="`Start processing (${Math.min(batchLimit, pending.length)} files)`"
            @click="processBatch"
          />
          <clean-up-table :columns="pendingColumns" :rows="pending" />
        </template>
        <k-box v-else theme="positive" text="Nothing to process. All images are within blueprint limits." />
      </section>

      <section v-if="started" class="clean-up-section">
        <k-headline>Batch results</k-headline>
        <k-box v-if="running" theme="info" text="Processing batch..." />
        <template v-if="batch">
          <k-box
            :theme="nextOffset === null ? 'positive' : 'info'"
            :text="`${batch.processed.length} files processed. ${nextOffset === null ? 'All batches complete.' : 'More files remaining.'}`"
          />
          <clean-up-table v-if="batch.processed.length" :columns="processedColumns" :rows="batch.processed" />
          <template v-if="batch.errors.length">
            <k-headline>Errors ({{ batch.errors.length }})</k-headline>
            <clean-up-table :columns="errorColumns" :rows="batch.errors" />
          </template>
        </template>
        <div class="clean-up-actions">
          <k-button
            v-if="nextOffset !== null"
            icon="arrow-right"
            variant="filled"
            text="Next batch"
            :disabled="running"
            @click="processBatch"
          />
          <k-button v-else-if="!running" icon="refresh" text="Scan again" @click="refresh" />
        </div>
      </section>

      <section v-if="!started && skip.length" class="clean-up-section">
        <k-headline>Skipped ({{ skip.length }})</k-headline>
        <clean-up-table :columns="skippedColumns" :rows="skip" />
      </section>
    </template>
  </k-panel-inside>
</template>

<script>
import CleanUpTable from "./CleanUpTable.vue";

export default {
  components: { CleanUpTable },
  data() {
    return {
      forceName: "",
      limit: 10,
      loading: false,
      running: false,
      pending: null,
      skip: [],
      batch: null,
      nextOffset: null,
      started: false,
      previewOptions: null,
      pendingColumns: [
        { key: "id", label: "File" },
        { key: "template", label: "Blueprint" },
        { key: "current", label: "Current" },
        { key: "actions", label: "Actions" }
      ],
      processedColumns: [
        { key: "id", label: "File" },
        { key: "template", label: "Blueprint" },
        { key: "actions", label: "Actions" }
      ],
      errorColumns: [
        { key: "id", label: "File" },
        { key: "error", label: "Error" }
      ],
      skippedColumns: [
        { key: "id", label: "File" },
        { key: "reason", label: "Reason" }
      ]
    };
  },
  computed: {
    batchLimit() {
      return Math.max(1, Math.min(100, Number(this.limit) || 10));
    },
    previewIsCurrent() {
      return this.previewOptions?.force === this.forceName && this.previewOptions?.limit === this.batchLimit;
    }
  },
  mounted() {
    this.refresh();
  },
  methods: {
    async refresh() {
      if (this.running || this.nextOffset !== null) return;
      this.loading = true;
      try {
        const result = await this.$api.get("clean-up/preview", {
          force: this.forceName || undefined
        });
        this.pending = result.pending;
        this.skip = result.skip;
        this.started = false;
        this.batch = null;
        this.previewOptions = { force: this.forceName, limit: this.batchLimit };
      } catch (error) {
        this.$panel.notification.error(error.message || "Could not scan images");
      } finally {
        this.loading = false;
      }
    },
    async processBatch() {
      if (this.running || !this.previewIsCurrent) return;
      if (!this.started && !window.confirm("This overwrites original images. Have you made a backup?")) return;

      this.running = true;
      this.started = true;
      try {
        const result = await this.$api.post("clean-up/batch", {
          force: this.previewOptions.force || null,
          limit: this.previewOptions.limit,
          offset: this.nextOffset || 0
        });
        this.batch = result;
        this.nextOffset = result.nextOffset;
        if (result.errors.length) {
          this.$panel.notification.error(`${result.errors.length} files could not be processed`);
        }
      } catch (error) {
        this.$panel.notification.error(error.message || "Batch processing failed");
      } finally {
        this.running = false;
      }
    }
  }
};
</script>

<style>
.clean-up-view { padding-bottom: 4rem; }
.clean-up-options { display: grid; grid-template-columns: minmax(12rem, 1fr) 10rem auto; align-items: end; gap: 1rem; max-width: 48rem; margin: 1.5rem 0; }
.clean-up-section { margin-top: 2rem; }
.clean-up-section .k-headline { margin-bottom: .75rem; }
.clean-up-section .clean-up-table { margin-top: 1rem; }
.clean-up-actions { margin-top: 1rem; }
@media (max-width: 45rem) {
  .clean-up-options { grid-template-columns: 1fr; }
}
</style>
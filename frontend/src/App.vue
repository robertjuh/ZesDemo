<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import {
  createCheckIn,
  generateRecommendation,
  getTodayCheckIn,
  type CheckIn,
  type CreateCheckInInput,
  type Recommendation,
} from './api'

const form = reactive<CreateCheckInInput>({
  energyLevel: 3,
  focusGoal: '',
  distractionRisk: '',
  notes: '',
})

const latestCheckIn = ref<CheckIn | null>(null)
const recommendation = ref<Recommendation | null>(null)
const error = ref<string | null>(null)
const submitting = ref(false)
const generating = ref(false)

onMounted(async () => {
  try {
    latestCheckIn.value = await getTodayCheckIn()
  } catch (e) {
    error.value = messageOf(e)
  }
})

async function submitCheckIn() {
  error.value = null
  recommendation.value = null
  submitting.value = true
  try {
    latestCheckIn.value = await createCheckIn({ ...form })
  } catch (e) {
    error.value = messageOf(e)
  } finally {
    submitting.value = false
  }
}

async function generate() {
  if (!latestCheckIn.value) {
    return
  }
  error.value = null
  generating.value = true
  try {
    recommendation.value = await generateRecommendation(latestCheckIn.value.id)
  } catch (e) {
    error.value = messageOf(e)
  } finally {
    generating.value = false
  }
}

function messageOf(e: unknown): string {
  return e instanceof Error ? e.message : 'Something went wrong.'
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString()
}
</script>

<template>
  <main class="app">
    <h1>Daily Coach Demo</h1>
    <p class="subtitle">
      Submit a daily check-in, then ask the coach for a structured recommendation.
    </p>

    <p v-if="error" class="error">{{ error }}</p>

    <section class="card">
      <h2>Daily check-in</h2>
      <form @submit.prevent="submitCheckIn">
        <label for="energy">Energy level: {{ form.energyLevel }} / 5</label>
        <input
          id="energy"
          class="slider"
          v-model.number="form.energyLevel"
          type="range"
          min="1"
          max="5"
          step="1"
        />

        <label for="goal">Focus goal</label>
        <input id="goal" v-model="form.focusGoal" placeholder="Prepare for Symfony interview" required />

        <label for="risk">Distraction risk</label>
        <input
          id="risk"
          v-model="form.distractionRisk"
          placeholder="Spending too much time overengineering"
          required
        />

        <label for="notes">Notes (optional)</label>
        <textarea id="notes" v-model="form.notes" placeholder="Need a small demo I can explain" />

        <div class="actions">
          <button type="submit" :disabled="submitting">
            {{ submitting ? 'Submitting…' : 'Submit check-in' }}
          </button>
          <button
            type="button"
            class="secondary"
            :disabled="!latestCheckIn || generating"
            @click="generate"
          >
            {{ generating ? 'Generating…' : 'Generate recommendation' }}
          </button>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Latest check-in</h2>
      <p v-if="!latestCheckIn" class="muted">No check-in yet today. Submit one above.</p>
      <dl v-else class="kv">
        <dt>Energy</dt>
        <dd>{{ latestCheckIn.energyLevel }} / 5</dd>
        <dt>Focus goal</dt>
        <dd>{{ latestCheckIn.focusGoal }}</dd>
        <dt>Distraction risk</dt>
        <dd>{{ latestCheckIn.distractionRisk }}</dd>
        <dt v-if="latestCheckIn.notes">Notes</dt>
        <dd v-if="latestCheckIn.notes">{{ latestCheckIn.notes }}</dd>
        <dt>Created</dt>
        <dd>{{ formatDate(latestCheckIn.createdAt) }}</dd>
      </dl>
    </section>

    <section class="card">
      <h2>Recommendation</h2>
      <p v-if="!recommendation" class="muted">
        Submit a check-in, then click “Generate recommendation”.
      </p>
      <dl v-else class="kv">
        <dt>Priority</dt>
        <dd><span class="badge" :class="recommendation.priority">{{ recommendation.priority }}</span></dd>
        <dt>Risk level</dt>
        <dd><span class="badge" :class="recommendation.riskLevel">{{ recommendation.riskLevel }}</span></dd>
        <dt>Next action</dt>
        <dd>{{ recommendation.nextAction }}</dd>
        <dt>Reasoning</dt>
        <dd>{{ recommendation.reasoning }}</dd>
      </dl>
    </section>
  </main>
</template>

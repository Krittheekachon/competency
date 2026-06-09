<template>
  <!-- ── Header ───────────────────────────────────────────────────────────── -->
  <div class="flex ic jb mb20">
    <div>
      <div class="sec-t">รายละเอียด IDP </div>
      <div class="sec-s">ภาพรวม · Timeline · ประวัติย้อนหลัง</div>
      <div
        style="display: inline-flex; align-items: center; gap: 6px; margin-top: 6px; padding: 4px 12px; background: var(--navy); border-radius: 20px"
      >
        <span style="font-size: 12px"></span>
        <span style="font-size: 12px; font-weight: 700; color: #fff">รอบประเมิน 2568</span>
        <span style="font-size: 11px; color: rgba(255,255,255,.6)">รอบปัจจุบัน</span>
      </div>
    </div>
    <button class="btn btn-s"> Export PDF</button>
  </div>

  <!-- ── Summary stats ─────────────────────────────────────────────────────── -->
  <div class="g4 mb20">
    <div class="sc" style="border-top: 3px solid var(--navy)">
      <div class="sl">กิจกรรมทั้งหมด</div>
      <div class="sv" style="color: var(--navy)">{{ cntTotal }}</div>
      <div class="ss muted">รอบปัจจุบัน</div>
    </div>
    <div class="sc" style="border-top: 3px solid var(--green)">
      <div class="sl">เสร็จสิ้น</div>
      <div class="sv gcc">{{ cntDone }}</div>
      <div class="ss muted">กิจกรรม</div>
    </div>
    <div class="sc" style="border-top: 3px solid var(--blue)">
      <div class="sl">กำลังดำเนินการ</div>
      <div class="sv bc">{{ cntInprog }}</div>
      <div class="ss muted">กิจกรรม</div>
    </div>
    <div class="sc" style="border-top: 3px solid var(--red)">
      <div class="sl">ไม่ผ่าน</div>
      <div class="sv rc">{{ cntFailed }}</div>
      <div class="ss muted">กิจกรรม</div>
    </div>
  </div>

  <!-- ── Timeline card ─────────────────────────────────────────────────────── -->
  <div class="card mb20">
    <div class="ch">
      <div>
        <div class="ct"> Timeline กิจกรรม IDP</div>
        <div class="cs">กดที่กิจกรรมเพื่อดูรายละเอียดและหลักฐาน</div>
      </div>
    </div>
    <div class="cb" style="padding: 0">
      <div
        v-for="(act, index) in timelineActs"
        :key="act.id"
        :style="{ borderBottom: index === timelineActs.length - 1 ? 'none' : '1px solid var(--border)' }"
      >
        <!-- Row -->
        <div
          style="display: flex; align-items: center; gap: 12px; padding: 14px 18px; cursor: pointer; transition: .15s"
          @click="toggleExpanded(act.id)"
        >
          <div
            style="width: 4px; height: 48px; border-radius: 2px; flex-shrink: 0"
            :style="{ background: getResultMeta(act.result).color }"
          />
          <div
            style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0"
            :style="{ background: act.bg }"
          >
            {{ act.ic }}
          </div>
          <div style="flex: 1; min-width: 0">
            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap">
              <span class="fw7 fs13">{{ act.t }}</span>
              <span :class="gapTagClass(act.gapT)" style="font-size: 9px">{{ act.gapT }}</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px; flex-wrap: wrap">
              <span style="font-size: 11px; color: var(--text3)">{{ act.gapCd }} · {{ act.gapN }}</span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px; flex-wrap: wrap">
              <span
                style="font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px"
                :style="{ background: getMethodMeta(act.m).bg, color: getMethodMeta(act.m).color }"
              >
                {{ act.m }}
              </span>
              <span style="font-size: 11px; color: var(--text3)">ครบ {{ act.due }}</span>
            </div>
          </div>
          <div style="text-align: center; flex-shrink: 0">
            <div style="font-size: 18px; font-weight: 800" :style="{ color: getResultMeta(act.result).color }">
              {{ act.logs.length }}
            </div>
            <div style="font-size: 10px; color: var(--text3)">บันทึก</div>
          </div>
          <span :class="`b ${getResultMeta(act.result).badge}`" style="flex-shrink: 0">
            {{ getResultMeta(act.result).label }}
          </span>
          <span style="font-size: 12px; color: var(--text3); flex-shrink: 0">
            {{ expandedId === act.id ? '▾' : '▸' }}
          </span>
        </div>

        <!-- Failed inline banner -->
        <div
          v-if="act.result === 'failed'"
          style="background: #FEF2F2; border-top: 1px solid #FECACA; padding: 10px 18px 10px 36px; display: flex; gap: 10px; align-items: flex-start"
        >
          <span style="font-size: 16px; flex-shrink: 0"></span>
          <div>
            <div class="fw7 fs12" style="color: var(--red)">
              ไม่ผ่าน · {{ act.rejectedBy }} · {{ act.rejectedDate }}
            </div>
            <div style="font-size: 12px; color: #991B1B; margin-top: 3px">
              "{{ act.rejectComment }}"
            </div>
          </div>
        </div>

        <!-- Expanded log panel -->
        <div
          v-if="expandedId === act.id"
          style="background: var(--bg); border-top: 1px solid var(--border)"
        >
          <div style="padding: 14px 18px 6px">
            <div
              style="font-size: 11px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 10px"
            >
              ประวัติการดำเนินงาน
            </div>
            <div style="position: relative; padding-left: 28px">
              <div
                style="position: absolute; left: 9px; top: 0; bottom: 0; width: 2px; background: var(--border); border-radius: 1px"
              />
              <div
                v-for="(log, logIdx) in act.logs"
                :key="logIdx"
                style="position: relative"
                :style="{ paddingBottom: logIdx === act.logs.length - 1 ? '0' : '14px' }"
              >
                <div
                  style="position: absolute; left: -24px; top: 2px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff"
                  :style="{
                    background:  logDotColor(log.type),
                    boxShadow: `0 0 0 2px ${logDotColor(log.type)}`,
                  }"
                />
                <div
                  style="background: #fff; border: 1px solid var(--border); border-radius: var(--r); padding: 10px 12px"
                  :style="logCardBorder(log.type)"
                >
                  <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px">
                    <div>
                      <div
                        style="font-size: 12px; font-weight: 600"
                        :style="{ color: logTextColor(log.type) }"
                      >
                        {{ log.n }}
                      </div>
                      <div style="font-size: 11px; color: var(--text3); margin-top: 2px">
                        by {{ log.by }}
                      </div>
                    </div>
                    <div style="font-size: 11px; color: var(--text3); font-weight: 600; white-space: nowrap; flex-shrink: 0">
                      {{ log.d }}
                    </div>
                  </div>
                  <div
                    v-if="log.evidence"
                    style="margin-top: 8px; padding: 6px 10px; background: var(--blue-lt); border-radius: 6px; display: flex; align-items: center; gap: 6px"
                  >
                    <span style="font-size: 14px"></span>
                    <span style="font-size: 11px; color: var(--blue); font-weight: 600">{{ log.evidence }}</span>
                    <span style="font-size: 10px; color: var(--text3); margin-left: auto">คลิกเพื่อดู →</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- ── History card ───────────────────────────────────────────────────────── -->
  <div class="card">
    <div class="ch" style="flex-direction: column; align-items: flex-start; gap: 12px">
      <div class="ct"> ประวัติ IDP ย้อนหลัง</div>
      <div style="display: flex; gap: 6px; flex-wrap: wrap">
        <button
          v-for="round in rounds"
          :key="round.id"
          type="button"
          style="padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; cursor: pointer; transition: .15s"
          :style="{
            border:      activeRound === round.id ? '1px solid transparent' : '1px solid var(--border)',
            background:  activeRound === round.id ? 'var(--navy)'           : 'var(--bg)',
            color:       activeRound === round.id ? '#fff'                  : 'var(--text2)',
          }"
          @click="activeRound = round.id"
        >
          {{ round.n }}{{ round.isCurrent ? ' (ปัจจุบัน)' : '' }}
        </button>
      </div>
    </div>

    <div class="cb" style="padding-top: 0">

      <!-- Current round -->
      <div v-if="activeRoundData.isCurrent" style="padding: 16px 0">
        <div
          v-for="gap in currentGaps"
          :key="gap.cd"
          style="padding: 14px 0; border-bottom: 1px solid var(--border)"
        >
          <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap">
            <span :class="gapTagClass(gap.t)">{{ gap.t }}</span>
            <span class="fw7 fs13">{{ gap.n }}</span>
            <span class="muted fs12">{{ gap.cd }} · Gap {{ gap.gapValue }}</span>
            <span :class="`b ${getStatusMeta(gap.status).cls}`" style="margin-left: auto">
              {{ getStatusMeta(gap.status).label }}
            </span>
          </div>
          <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px">
            <div class="pw" style="flex: 1; height: 6px">
              <div
                class="pb"
                :style="{
                  width:      `${gap.pct}%`,
                  background: gap.pct === 100 ? 'var(--green)' : 'var(--blue)',
                }"
              />
            </div>
            <span style="font-size: 11px; color: var(--text3)">
              เสร็จแล้ว {{ gap.doneCount }}/{{ gap.acts.length }} กิจกรรม
            </span>
          </div>
          <div
            v-if="gap.acts.length"
            style="margin-top: 10px; display: flex; flex-direction: column; gap: 6px"
          >
            <div
              v-for="(act, actIdx) in gap.acts"
              :key="actIdx"
              style="display: flex; align-items: center; gap: 8px; padding: 7px 10px; background: var(--bg); border-radius: var(--r); border: 1px solid var(--border)"
            >
              <span style="font-size: 16px">{{ act.ic }}</span>
              <div style="flex: 1">
                <div class="fw6 fs12">{{ act.t }}</div>
                <div class="muted fs11">{{ act.m }} · ครบ {{ act.due }}</div>
              </div>
              <span :class="`b ${getResultMeta(act.result).badge}`">
                {{ getResultMeta(act.result).label }}
              </span>
              <span class="muted fs11">{{ act.logs.length }} บันทึก</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Past rounds -->
      <div v-else style="padding: 16px 0">
        <div
          style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: var(--green-bg); border: 1px solid var(--green-md); border-radius: var(--r); margin-bottom: 16px"
        >
          <span></span>
          <span style="font-size: 12px; font-weight: 700; color: var(--green)">
            {{ activeRoundData.n }} — เสร็จสิ้นแล้ว
          </span>
        </div>

        <div
          v-for="(gap, idx) in activeRoundData.gaps || []"
          :key="`${gap.cd}-${idx}`"
          style="padding: 14px 0; border-bottom: 1px solid var(--border)"
        >
          <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px">
            <span :class="gapTagClass(gap.t)">{{ gap.t }}</span>
            <span class="fw7 fs13">{{ gap.n }}</span>
            <span class="muted fs12">{{ gap.cd }} · Gap {{ gap.gap }}</span>
            <span class="b bg" style="margin-left: auto">เสร็จสิ้น</span>
          </div>

          <div
            v-for="(act, actIdx) in gap.acts"
            :key="actIdx"
            style="margin-bottom: 8px; padding: 8px 12px; background: var(--bg); border-radius: var(--r); border-left: 3px solid var(--green)"
          >
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px">
              <span style="font-size: 15px">{{ act.ic }}</span>
              <span class="fw6 fs12">{{ act.t }}</span>
              <span class="muted fs11">{{ act.m }}</span>
              <span class="b bg" style="margin-left: auto; font-size: 9px">ผ่านแล้ว ✓</span>
            </div>

            <div
              v-for="(log, logIdx) in act.logs"
              :key="logIdx"
              style="display: flex; gap: 8px; padding: 6px 0; border-top: 1px dashed var(--border); align-items: flex-start"
            >
              <span style="width: 56px; font-size: 10px; color: var(--text3); flex-shrink: 0; padding-top: 1px">
                {{ log.d }}
              </span>
              <div style="flex: 1">
                <div
                  style="font-size: 11px"
                  :style="{
                    color:       log.type === 'done' ? 'var(--green)' : 'var(--text2)',
                    fontWeight:  log.type === 'done' ? 700 : 400,
                  }"
                >
                  {{ log.n }}
                </div>
                <div
                  v-if="log.evidence"
                  style="margin-top: 4px; display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; background: var(--bg); border: 1px solid var(--border); border-radius: 20px"
                >
                  <span style="font-size: 12px"></span>
                  <span style="font-size: 11px; color: var(--blue); font-weight: 600">{{ log.evidence }}</span>
                  <span style="font-size: 10px; color: var(--text3)">↗</span>
                </div>
              </div>
              <span style="font-size: 10px; color: var(--text3); flex-shrink: 0; white-space: nowrap">
                by {{ log.by }}
              </span>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { IDP_GAPS_DATA, IDP_ACTIVITIES_DATA } from '../../data'

// ── State ─────────────────────────────────────────────────────────────────────

const expandedId = ref<string | null>(null)
const activeRound = ref('current')

// ── Static past rounds data ───────────────────────────────────────────────────

const pastRounds: any[] = []

const rounds = [
  { id: 'current', n: 'รอบประเมิน 2568', isCurrent: true, gaps: [] as any[] },
  ...pastRounds,
]

// ── Derived ───────────────────────────────────────────────────────────────────

const activeRoundData = computed(
  () => rounds.find(r => r.id === activeRound.value) ?? rounds[0]
)

const timelineActs = IDP_GAPS_DATA.flatMap((gap) =>
  (IDP_ACTIVITIES_DATA[gap.cd] || []).map((act: any, idx: number) => ({
    ...act,
    id: `${gap.cd}-${idx}`,
    gapCd: gap.cd,
    gapN:  gap.n,
    gapT:  gap.t,
  }))
)

const cntTotal  = timelineActs.length
const cntDone   = timelineActs.filter(a => a.result === 'done').length
const cntFailed = timelineActs.filter(a => a.result === 'failed').length
const cntInprog = cntTotal - cntDone - cntFailed

const currentGaps = IDP_GAPS_DATA.map((gap) => {
  const acts     = IDP_ACTIVITIES_DATA[gap.cd] || []
  const doneCount = acts.filter((a: any) => a.result === 'done').length
  const pct      = acts.length ? Math.round((doneCount / acts.length) * 100) : 0
  return { ...gap, gapValue: Math.max(gap.exp - gap.actual, 0), acts, doneCount, pct }
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function toggleExpanded(id: string) {
  expandedId.value = expandedId.value === id ? null : id
}

function gapTagClass(type: string) {
  if (type === 'CC')  return 'tag-cc'
  if (type === 'MC')  return 'tag-mc'
  if (type === 'FC1') return 'tag-fc1'
  if (type === 'FC2') return 'tag-fc2'
  return 'tag-fc'
}

function getResultMeta(result: string | null) {
  if (result === 'done')   return { color: 'var(--green)', bg: 'var(--green-bg)', label: 'เสร็จสิ้น',        badge: 'bg' }
  if (result === 'failed') return { color: 'var(--red)',   bg: 'var(--red-bg)',   label: 'ไม่ผ่าน',          badge: 'br' }
  return                          { color: 'var(--blue)',  bg: 'var(--blue-lt)',  label: 'กำลังดำเนินการ', badge: 'bt' }
}

function getMethodMeta(method: string) {
  if (method === 'Formal Learning') return { color: 'var(--blue)',   bg: 'var(--blue-lt)' }
  if (method === 'Social Learning') return { color: 'var(--green)',  bg: 'var(--green-bg)' }
  return                                   { color: 'var(--orange)', bg: '#FFF7ED' }
}

function getStatusMeta(status: string) {
  if (status === 'submitted') return { cls: 'by', label: 'รออนุมัติ' }
  if (status === 'approved')  return { cls: 'bg', label: 'อนุมัติแล้ว' }
  if (status === 'rejected')  return { cls: 'br', label: 'ไม่ผ่าน' }
  return                             { cls: 'bb', label: 'Draft' }
}

function logDotColor(type: string) {
  if (type === 'reject') return 'var(--red)'
  if (type === 'done')   return 'var(--green)'
  return 'var(--blue)'
}

function logTextColor(type: string) {
  if (type === 'reject') return 'var(--red)'
  if (type === 'done')   return 'var(--green)'
  return 'var(--text)'
}

function logCardBorder(type: string): Record<string, string> {
  if (type === 'reject') return { borderLeft: '3px solid var(--red)' }
  if (type === 'done')   return { borderLeft: '3px solid var(--green)' }
  return {}
}
</script>


<script setup lang="ts">
import { computed } from 'vue';
import { IDP_GAPS_DATA, INITIAL_COMPETENCIES } from '../../data';

const props = defineProps<{ setPage: (p: string) => void }>();

type GapRow = {
  cd: string;
  n: string;
  t: string;
  ss: number | null;
  sup: number;
  exp: number;
  gap: number;
  pri: string;
};

const supervisorScores = new Map(IDP_GAPS_DATA.map((gap: any) => [gap.cd, gap]));

const gaps = computed<GapRow[]>(() => {
  return INITIAL_COMPETENCIES.map((competency: any) => {
    const assessed = supervisorScores.get(competency.cd) as any;
    const expected = Number(assessed?.exp ?? competency.lv ?? 0);
    const supervisorScore = Number(assessed?.actual ?? expected);

    return {
      cd: competency.cd,
      n: competency.n,
      t: competency.t,
      ss: null,
      sup: supervisorScore,
      exp: expected,
      gap: supervisorScore - expected,
      pri: assessed?.pri ?? 'low',
    };
  });
});

const passCount = computed(() => gaps.value.filter((g) => g.gap >= 0).length);
const failCount = computed(() => gaps.value.filter((g) => g.gap < 0).length);
const needIDP = computed(() => gaps.value.filter((g) => g.gap < 0).sort((a, b) => a.gap - b.gap));

const getTagClass = (t: string) => t === 'CC' ? 'tag-cc' : t === 'MC' ? 'tag-mc' : t === 'FC1' ? 'tag-fc1' : t === 'FC2' ? 'tag-fc2' : 'tag-fc';
</script>

<template>
  <div>
    <div class="flex ic jb mb20">
      <div>
        <div class="sec-t">สรุปผลสมรรถนะ 📊</div>
        <div class="sec-s">ยืนยันโดย รศ.ดร.วิไล ใจดี · 5 พ.ค. 2568 · สถานะ: approved</div>
      </div>
      <button class="btn btn-s">📥 Export PDF</button>
    </div>

    <div class="g2 mb14">
      <div class="sc" style="border-left: 4px solid var(--teal);">
        <div class="sl">ผ่านเกณฑ์</div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
          <div class="sv tc">{{ passCount }}</div>
          <div style="font-size: 13px; color: var(--text3);">/ {{ gaps.length }} สมรรถนะ</div>
        </div>
        <div class="ss muted">รวมจุดแข็งและที่ทำได้ตามเกณฑ์</div>
      </div>
      <div class="sc" style="border-left: 4px solid var(--red);">
        <div class="sl">ไม่ผ่านเกณฑ์</div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
          <div class="sv rc">{{ failCount }}</div>
          <div style="font-size: 13px; color: var(--text3);">/ {{ gaps.length }} สมรรถนะ</div>
        </div>
        <div class="ss muted">ต้องจัดทำ IDP พัฒนาต่อ</div>
      </div>
    </div>

    <div class="card mb14">
      <div class="ch"><div class="ct">ผลรายสมรรถนะ</div></div>
      <div style="overflow-x: auto;">
        <table class="tbl">
          <thead>
            <tr>
              <th>สมรรถนะ</th>
              <th style="text-align: center;">ประเภท</th>
              <th style="text-align: center;">ระดับคาดหวัง</th>
              <th style="text-align: center;">ประเมินตนเอง</th>
              <th style="text-align: center;">ผู้บังคับบัญชา</th>
              <th style="text-align: center;">Competency Gap</th>
              <th style="text-align: center;">Priority</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(g, i) in gaps" :key="i">
              <td>
                <div class="fw6 fs13">{{ g.n }}</div>
                <div class="muted fs11">{{ g.cd }}</div>
              </td>
              <td style="text-align: center;">
                <span :class="getTagClass(g.t)">{{ g.t }}</span>
              </td>
              <td style="text-align: center;">
                <span style="display: inline-flex; width: 30px; height: 30px; border-radius: 8px; background: var(--navy); color: #fff; font-size: 14px; font-weight: 800; align-items: center; justify-content: center;">{{ g.exp }}</span>
              </td>
              <td style="text-align: center;">
                <span style="display: inline-flex; min-width: 30px; height: 30px; border-radius: 8px; background: var(--blue-lt); color: var(--blue); font-size: 13px; font-weight: 800; align-items: center; justify-content: center; padding: 0 8px;">{{ g.ss ?? '—' }}</span>
              </td>
              <td style="text-align: center;">
                <span :style="{ display: 'inline-flex', width: '30px', height: '30px', borderRadius: '8px', background: g.gap >= 0 ? 'var(--green-bg)' : 'var(--red-bg)', color: g.gap >= 0 ? 'var(--green)' : 'var(--red)', fontSize: '14px', fontWeight: 800, alignItems: 'center', justifyContent: 'center' }">{{ g.sup }}</span>
              </td>
              <td style="text-align: center;">
                <span :class="['b', g.gap >= 0 ? 'bt' : g.gap <= -2 ? 'br' : 'by']">
                  {{ g.gap > 0 ? `+${g.gap}` : g.gap }}
                </span>
              </td>
              <td style="text-align: center;">
                <span v-if="g.gap >= 0" class="b bt">ผ่านเกณฑ์</span>
                <span v-else-if="g.pri === 'high'" class="b br">เร่งด่วน</span>
                <span v-else class="b by">ต้องพัฒนา</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="ch">
        <div>
          <div class="ct">⚠ สมรรถนะที่ต้องทำ IDP</div>
          <div class="cs">สมรรถนะที่ยังไม่ผ่านเกณฑ์การประเมิน</div>
        </div>
        <div style="margin-left: auto;">
          <button class="btn btn-t btn-sm" @click="props.setPage('emp-idp')">สร้าง IDP →</button>
        </div>
      </div>
      <div class="cb">
        <div
          v-for="(g, i) in needIDP"
          :key="i"
          style="display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border);"
        >
          <div :style="{ width: '22px', height: '22px', borderRadius: '50%', background: g.pri === 'high' ? 'var(--red)' : 'var(--yellow)', color: '#fff', fontSize: '10px', fontWeight: 800, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }">{{ i + 1 }}</div>
          <div style="flex: 1;">
            <span class="fw6 fs13">{{ g.n }}</span>
            <span class="muted fs12" style="margin-left: 8px;">{{ g.cd }}</span>
          </div>
          <span :class="['b', getTagClass(g.t)]">{{ g.t }}</span>
          <span :class="['b', g.pri === 'high' ? 'br' : 'by']">Gap {{ g.gap > 0 ? `+${g.gap}` : g.gap }} · คาดหวัง {{ g.exp }} / ผู้บังคับบัญชา {{ g.sup }}</span>
          <span :class="['b', g.pri === 'high' ? 'br' : 'by']" style="margin-left: 4px;">{{ g.pri === 'high' ? 'เร่งด่วน' : 'ต้องพัฒนา' }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

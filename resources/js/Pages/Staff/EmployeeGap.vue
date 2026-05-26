<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ setPage: (p: string) => void }>();

type CompetencyGap = {
  cd: string;
  n: string;
  t: string;
  ss: number;
  sup: number;
  exp: number;
  pri: string;
};

const gaps: CompetencyGap[] = [];

const passCount = computed(() => gaps.filter((g) => g.sup >= g.exp).length);
const failCount = computed(() => gaps.filter((g) => g.sup < g.exp).length);
const needIDP = computed(() =>
  gaps
    .filter((g) => g.sup < g.exp)
    .sort((a, b) => (a.sup - a.exp) - (b.sup - b.exp)),
);

const getTagClass = (t: string) =>
  t === 'CC' ? 'tag-cc' : t === 'MC' ? 'tag-mc' : t === 'FC1' ? 'tag-fc1' : t === 'FC2' ? 'tag-fc2' : 'tag-fc';
</script>

<template>
  <div>
    <div class="flex ic jb mb20">
      <div>
        <div class="sec-t">สรุปผลสมรรถนะ 📊</div>
        <div class="sec-s">รอดึงข้อมูลผลการประเมินจากฐานข้อมูล</div>
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
        <div class="ss muted">รอดึงข้อมูลจุดแข็งและผลประเมินที่ผ่านเกณฑ์</div>
      </div>
      <div class="sc" style="border-left: 4px solid var(--red);">
        <div class="sl">ไม่ผ่านเกณฑ์</div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
          <div class="sv rc">{{ failCount }}</div>
          <div style="font-size: 13px; color: var(--text3);">/ {{ gaps.length }} สมรรถนะ</div>
        </div>
        <div class="ss muted">รอดึงข้อมูลสมรรถนะที่ต้องจัดทำ IDP</div>
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
              <th style="text-align: center;">สถานะ</th>
              <th style="text-align: center;">Priority</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="gaps.length === 0">
              <td colspan="7" class="muted fs13" style="text-align: center; padding: 28px;">
                ยังไม่มีข้อมูลผลรายสมรรถนะ
              </td>
            </tr>
            <tr v-for="(g, i) in gaps" :key="i">
              <td>
                <div class="fw6 fs13">{{ g.n }}</div>
                <div class="muted fs11">{{ g.cd }}</div>
              </td>
              <td style="text-align: center;">
                <span :class="getTagClass(g.t)">{{ g.t }}</span>
              </td>
              <td style="text-align: center;">{{ g.exp }}</td>
              <td style="text-align: center;">{{ g.ss }}</td>
              <td style="text-align: center;">{{ g.sup }}</td>
              <td style="text-align: center;">{{ g.sup - g.exp >= 0 ? '✓' : '✕' }}</td>
              <td style="text-align: center;">
                <span v-if="g.sup - g.exp >= 0" class="b bt">ผ่านเกณฑ์</span>
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
        <div v-if="needIDP.length === 0" class="muted fs13" style="text-align: center; padding: 16px;">
          ยังไม่มีข้อมูลสมรรถนะที่ต้องทำ IDP
        </div>
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
          <span :class="['b', g.pri === 'high' ? 'br' : 'by']">เป้าหมาย: Level {{ g.exp }} (ปัจจุบัน {{ g.sup }})</span>
          <span :class="['b', g.pri === 'high' ? 'br' : 'by']" style="margin-left: 4px;">{{ g.pri === 'high' ? 'เร่งด่วน' : 'ต้องพัฒนา' }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<template>
    <slot />
</template>

<script lang="ts">
import {
    computed,
    defineComponent,
    ref,
    watch,
    type CSSProperties,
    type PropType,
} from 'vue';

interface AutoSelectProps {
    label: string;
    value: string;
    onChange: (val: string) => void;
    options: string[];
    placeholder?: string;
    required?: boolean;
    style?: CSSProperties;
}

export const AutoSelect = defineComponent({
    name: 'AutoSelect',
    props: {
        label: {
            type: String,
            required: true,
        },
        value: {
            type: String,
            required: true,
        },
        onChange: {
            type: Function as PropType<AutoSelectProps['onChange']>,
            required: true,
        },
        options: {
            type: Array as PropType<string[]>,
            required: true,
        },
        placeholder: {
            type: String,
            default: '',
        },
        required: {
            type: Boolean,
            default: false,
        },
        style: {
            type: Object as PropType<CSSProperties>,
            default: () => ({}),
        },
    },
    setup(props) {
        const inputValue = ref(props.value || '');
        const isOpen = ref(false);
        const containerRef = ref<HTMLDivElement | null>(null);
        const upward = ref(false);

        watch(
            () => props.value,
            (value) => {
                inputValue.value = value || '';
            },
        );

        watch(isOpen, (open) => {
            if (!open || !containerRef.value) return;

            const rect = containerRef.value.getBoundingClientRect();
            upward.value = window.innerHeight - rect.bottom < 250;
        });

        const containerStyle = computed<CSSProperties>(() => ({
            position: 'relative',
            minWidth: '180px',
            ...props.style,
        }));

        const arrowStyle = computed<CSSProperties>(() => ({
            position: 'absolute',
            right: '10px',
            top: '50%',
            transform: `translateY(-50%) ${isOpen.value ? 'rotate(180deg)' : ''}`,
            pointerEvents: 'none',
            color: 'var(--text3)',
            display: 'flex',
            transition: 'transform 0.2s',
        }));

        const dropdownStyle = computed<CSSProperties>(() => ({
            position: 'absolute',
            bottom: upward.value ? 'calc(100% + 4px)' : 'auto',
            top: upward.value ? 'auto' : 'calc(100% + 4px)',
            left: 0,
            right: 0,
            zIndex: 1000,
            background: '#fff',
            border: '1px solid var(--border)',
            borderRadius: 'var(--r)',
            boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
            maxHeight: '260px',
            overflowY: 'auto',
            animation: upward.value ? 'slideUp 0.1s ease-out' : 'slideDown 0.1s ease-out',
        }));

        const filtered = computed(() =>
            props.options.filter((opt) =>
                opt.toLowerCase().includes(inputValue.value.toLowerCase()),
            ),
        );

        const optionStyle = (opt: string): CSSProperties => ({
            padding: '12px 14px',
            cursor: 'pointer',
            fontSize: '13px',
            borderBottom: '1px solid #f8fafc',
            background: opt === props.value ? 'var(--blue-lt)' : 'transparent',
            color: opt === props.value ? 'var(--blue)' : 'var(--text1)',
            fontWeight: opt === props.value ? 600 : 400,
            transition: 'background 0.2s',
        });

        const handleInput = (event: Event) => {
            const target = event.target as HTMLInputElement;
            inputValue.value = target.value;
            isOpen.value = true;
        };

        const selectOption = (event: MouseEvent, opt: string) => {
            event.preventDefault();
            props.onChange(opt);
            inputValue.value = opt;
            isOpen.value = false;
        };

        const handleMouseEnter = (event: MouseEvent, opt: string) => {
            if (opt !== props.value) {
                (event.currentTarget as HTMLElement).style.background = '#f1f5f9';
            }
        };

        const handleMouseLeave = (event: MouseEvent, opt: string) => {
            if (opt !== props.value) {
                (event.currentTarget as HTMLElement).style.background = 'transparent';
            }
        };

        const closeDropdown = () => {
            isOpen.value = false;
            inputValue.value = props.value || '';
        };

        return {
            inputValue,
            isOpen,
            containerRef,
            containerStyle,
            arrowStyle,
            dropdownStyle,
            filtered,
            optionStyle,
            handleInput,
            selectOption,
            handleMouseEnter,
            handleMouseLeave,
            closeDropdown,
        };
    },
    template: `
        <div ref="containerRef" class="fg" :style="containerStyle">
            <label class="lbl">{{ label }}</label>
            <div style="position: relative">
                <input
                    class="inp"
                    :value="inputValue"
                    :placeholder="placeholder"
                    :required="required"
                    autocomplete="off"
                    @input="handleInput"
                    @focus="isOpen = true"
                />
                <div :style="arrowStyle">
                    <svg width="16" height="16" fill="none" viewBox="0 0 20 20">
                        <path
                            stroke="currentColor"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.5"
                            d="m6 8 4 4 4-4"
                        />
                    </svg>
                </div>
            </div>

            <template v-if="isOpen">
                <div :style="dropdownStyle">
                    <template v-if="filtered.length > 0">
                        <div
                            v-for="opt in filtered"
                            :key="opt"
                            :style="optionStyle(opt)"
                            @mousedown="selectOption($event, opt)"
                            @mouseenter="handleMouseEnter($event, opt)"
                            @mouseleave="handleMouseLeave($event, opt)"
                        >
                            {{ opt }}
                        </div>
                    </template>
                    <div v-else class="empty-option">
                        ไม่พบข้อมูล
                    </div>
                </div>
                <div class="dropdown-backdrop" @mousedown="closeDropdown" />
            </template>
        </div>
    `,
});

export const ExcelImportModal = defineComponent({
    name: 'ExcelImportModal',
    props: {
        title: {
            type: String,
            required: true,
        },
        templateName: {
            type: String,
            required: true,
        },
        templateFile: {
            type: String,
            default: '',
        },
        onClose: {
            type: Function as PropType<() => void>,
            required: true,
        },
    },
    setup(props) {
        const selectedFile = ref<File | null>(null);
        const fileInputRef = ref<HTMLInputElement | null>(null);

        const importButtonStyle = computed<CSSProperties>(() => ({
            minWidth: '140px',
            borderRadius: 'var(--r)',
            opacity: selectedFile.value ? 1 : 0.5,
            cursor: selectedFile.value ? 'pointer' : 'not-allowed',
        }));

        const selectedFileSize = computed(() => {
            if (!selectedFile.value) return '';

            return `ขนาดไฟล์: ${(selectedFile.value.size / 1024).toFixed(2)} KB`;
        });

        const downloadTemplate = () => {
            const link = document.createElement('a');
            link.href = props.templateFile || `/templates/${props.templateName}`;
            link.download = props.templateName;
            link.click();
        };

        const handleFileChange = (event: Event) => {
            const target = event.target as HTMLInputElement;
            const file = target.files?.[0];

            if (file) {
                selectedFile.value = file;
            }
        };

        const openFileDialog = () => {
            fileInputRef.value?.click();
        };

        const handleImport = () => {
            if (!selectedFile.value) return;

            alert(`นำเข้าไฟล์ "${selectedFile.value.name}" เรียบร้อยแล้ว! (Mock Import)`);
            props.onClose();
        };

        return {
            selectedFile,
            fileInputRef,
            importButtonStyle,
            selectedFileSize,
            downloadTemplate,
            handleFileChange,
            openFileDialog,
            handleImport,
        };
    },
    template: `
        <div class="mo shared-import-modal">
            <div class="mo-box anim-fade-in import-box">
                <div class="mo-h import-header">
                    <div>
                        <div class="fw8 fs18 import-title">{{ title }}</div>
                        <div class="fs12 muted mt4">
                            อัปโหลดไฟล์ Excel เพื่อนำเข้าข้อมูลเข้าสู่ระบบโดยตรง
                        </div>
                    </div>
                    <button class="btn-close" type="button" @click="onClose">✕</button>
                </div>

                <div class="mo-b import-body">
                    <div class="flex ic jb mb24 p16 template-card">
                        <div class="flex ic g12">
                            <div class="template-icon">📄</div>
                            <div>
                                <div class="fw8 fs14 template-title">ไฟล์แม่แบบ (Template)</div>
                                <div class="fs11 muted">ดาวน์โหลดเพื่อเตรียมข้อมูลให้ถูกต้อง</div>
                            </div>
                        </div>
                        <button
                            class="btn btn-s btn-sm flex ic g8 download-button"
                            type="button"
                            @click="downloadTemplate"
                        >
                            <span class="download-icon">📥</span>
                            ดาวน์โหลดตาราง
                        </button>
                    </div>

                    <div class="fg">
                        <label class="lbl mb12 flex ic jb">
                            <span>เลือกไฟล์จากคอมพิวเตอร์ของคุณ</span>
                            <span class="fs11 fw4 muted">รองรับไฟล์ .xlsx, .csv</span>
                        </label>

                        <input
                            ref="fileInputRef"
                            type="file"
                            class="hidden-file-input"
                            accept=".xlsx, .xls, .csv"
                            @change="handleFileChange"
                        />

                        <div class="upload-dropzone" @click="openFileDialog">
                            <div class="flex col ic jc upload-content">
                                <div class="upload-icon-pulse mb20">
                                    {{ selectedFile ? '✅' : '📊' }}
                                </div>
                                <div class="fw8 fs16 mb8 upload-title">
                                    {{ selectedFile ? selectedFile.name : 'ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อค้นหา' }}
                                </div>
                                <div class="muted fs12 upload-desc">
                                    <template v-if="selectedFile">
                                        {{ selectedFileSize }}
                                    </template>
                                    <template v-else>
                                        ระบบจะตรวจสอบหัวข้อในตารางอัตโนมัติ<br />
                                        กรุณาตรวจสอบให้แน่ใจว่าไม่มีเซลล์ที่ว่างเปล่าในคอลัมน์ที่จำเป็น
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex g12 mt32 import-actions">
                        <button
                            class="btn btn-s"
                            type="button"
                            style="min-width: 100px; border-radius: var(--r)"
                            @click="onClose"
                        >
                            ยกเลิก
                        </button>
                        <button
                            class="btn btn-p shadow-sm"
                            type="button"
                            :disabled="!selectedFile"
                            :style="importButtonStyle"
                            @click="handleImport"
                        >
                            เริ่มนำเข้าข้อมูล
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `,
});
</script>

<script setup lang="ts">
defineOptions({
    name: 'SharedUI',
});
</script>

<style>
.dropdown-backdrop {
    position: fixed;
    inset: 0;
    z-index: 900;
}

.empty-option {
    padding: 14px;
    color: #94a3b8;
    font-size: 13px;
    text-align: center;
}

.shared-import-modal {
    z-index: 1000;
}

.import-box {
    width: 550px;
    border-radius: 24px;
}

.import-header {
    padding: 24px 28px;
    border-bottom: 1px solid #f1f5f9;
}

.import-title {
    color: var(--navy);
}

.import-body {
    padding: 28px;
}

.template-card {
    background: #f8fafc;
    border-radius: var(--r);
    border: 1px solid #e2e8f0;
}

.template-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    font-size: 20px;
}

.template-title {
    color: var(--text2);
}

.download-button {
    padding: 8px 16px;
    border-radius: 10px;
    font-weight: 700;
    border: 1.5px solid var(--blue);
    color: var(--blue);
}

.download-icon {
    font-size: 16px;
}

.hidden-file-input {
    display: none;
}

.upload-content {
    padding: 60px 40px;
}

.upload-title {
    color: var(--navy);
}

.upload-desc {
    max-width: 280px;
    text-align: center;
    line-height: 1.6;
}

.import-actions {
    justify-content: flex-end;
}

.btn-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 50%;
    background: #f1f5f9;
    color: var(--text3);
    cursor: pointer;
    transition: 0.2s;
    font-size: 14px;
}

.btn-close:hover {
    background: #fee2e2;
    color: #ef4444;
    transform: rotate(90deg);
}

.upload-dropzone {
    border: 2.5px dashed #e2e8f0;
    border-radius: 20px;
    transition: 0.3s;
    cursor: pointer;
    background: #fafafa;
}

.upload-dropzone:hover {
    border-color: var(--blue);
    background: var(--blue-lt);
}

.upload-icon-pulse {
    font-size: 48px;
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.1));
    animation: pulse 2s infinite;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 0.8;
    }
    50% {
        transform: scale(1.1);
        opacity: 1;
    }
    100% {
        transform: scale(1);
        opacity: 0.8;
    }
}
</style>

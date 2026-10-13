import type { Shipment } from "../types";
export const labels: Record<string, string> = {
  inspection: "Menunggu pemeriksaan",
  pending_approval: "Menunggu persetujuan",
  approved: "Siap ditugaskan",
  assigned: "Menunggu jawaban Satria",
  accepted: "Siap diambil",
  in_transit: "Dalam perjalanan",
  arrived: "Di lokasi penerima",
  held: "Ditahan sementara",
  returning: "Dalam proses retur",
  returned: "Sudah dikembalikan",
  delivered: "Sudah diterima",
};
export const roleLabels = {
  admin: "Admin Pharma",
  courier: "Satria Pharma",
  buyer: "Pembeli",
};
export function tone(status: string) {
  if (["held", "returning"].includes(status)) return "danger";
  if (["inspection", "pending_approval", "assigned"].includes(status)) return "warning";
  if (["delivered", "approved", "accepted"].includes(status)) return "success";
  return "neutral";
}
export function dateTime(value: string | null | undefined) {
  if (!value) return "Belum tercatat";
  const d = new Date(value);
  return Number.isNaN(d.valueOf())
    ? "Waktu belum tersedia"
    : new Intl.DateTimeFormat("id-ID", {
        day: "2-digit",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
        timeZone: "Asia/Jakarta",
      }).format(d) + " WIB";
}
export function nextStep(s: Shipment): string {
  return (
    (
      {
        inspection: "Petugas perlu memeriksa kemasan, segel, dan label.",
        pending_approval: "Pemeriksaan selesai. Admin perlu memberi persetujuan.",
        approved: "Admin dapat memilih Satria untuk mengantar paket.",
        assigned: "Tunggu Satria menerima atau menolak tugas.",
        accepted: "Satria siap mengambil paket sesuai instruksi.",
        in_transit: "Paket sedang diantar. Pastikan penerima siap.",
        arrived: "Periksa segel bersama penerima sebelum serah terima.",
        held: "Paket tidak dilanjutkan sampai Admin memberi keputusan.",
        returning: "Satria perlu mengembalikan paket sesuai instruksi Admin.",
        returned: "Paket sudah dikembalikan. Riwayat retur tersimpan.",
        delivered: "Paket sudah diterima. Pembeli dapat mengonfirmasi penerimaan.",
      } as Record<string, string>
    )[s.status] || "Buka riwayat untuk melihat pembaruan terakhir."
  );
}
export const issueLabels: Record<string, string> = {
  seal: "Segel tidak utuh",
  packaging: "Kemasan rusak",
  temperature: "Suhu di luar instruksi",
  label: "Label tidak sesuai",
  recipient_unavailable: "Penerima belum tersedia",
  address: "Alamat perlu dikonfirmasi",
  other: "Kendala lainnya",
};

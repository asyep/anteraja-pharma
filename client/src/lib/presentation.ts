import type { Shipment } from "../types";
export const labels: Record<string, string> = {
  cancelled: "Dibatalkan Admin",
  inspection: "Menunggu pemeriksaan",
  pending_approval: "Menunggu persetujuan",
  approved: "Siap ditugaskan",
  assigned: "Menunggu konfirmasi kurir",
  accepted: "Kurir terpilih",
  in_transit: "Dalam perjalanan",
  arrived: "Di lokasi penerima",
  held: "Ditahan sementara",
  returning: "Dalam proses retur",
  returned: "Sudah dikembalikan",
  delivered: "Sudah diterima",
};
export const roleLabels = {
  admin: "Admin Pharma",
  checker: "Checker",
  courier: "Kurir",
  buyer: "Vendor / Customer",
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
        approved: "Admin dapat memilih kurir untuk mengantar paket.",
        assigned: "Tunggu kurir menerima atau menolak tugas.",
        accepted: "Kurir terpilih siap mengambil paket sesuai instruksi.",
        in_transit: "Paket sedang diantar. Pastikan penerima siap.",
        arrived: "Periksa segel bersama penerima sebelum serah terima.",
        held: "Paket tidak dilanjutkan sampai Admin memberi keputusan.",
        returning: "Kurir perlu mengembalikan paket sesuai instruksi Admin.",
        returned: "Paket sudah dikembalikan. Riwayat retur tersimpan.",
        delivered: "Paket sudah diterima. Vendor / Customer dapat melihat bukti pengiriman.",
        cancelled: "Kiriman dibatalkan sebelum penugasan. Alasan tersimpan di riwayat.",
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

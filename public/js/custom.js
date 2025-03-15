function copyReferralLink() {
    var copyText = document.getElementById("referralLink");
    copyText.select();
    document.execCommand("copy");
    alert("Referral link copied: " + copyText.value);
}

const hre = require("hardhat");

async function main() {
  const [deployer] = await hre.ethers.getSigners();
  console.log("Deploying VoteContract with account:", deployer.address);

  console.log("Deploying VoteContract...");
  const VoteContract = await hre.ethers.getContractFactory("VoteContract");
  const voteContract = await VoteContract.deploy();

  // Wait for the deployment transaction to be mined
  const txReceipt = await voteContract.deploymentTransaction().wait();
  console.log("Transaction receipt:", txReceipt);

  // Use txReceipt.contractAddress to get the deployed address
  console.log("VoteContract deployed to:", txReceipt.contractAddress);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
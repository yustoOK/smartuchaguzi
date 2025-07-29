const hre = require("hardhat");

async function main() {
  // Getting the deployer account
  const [deployer] = await hre.ethers.getSigners();
  console.log("Deploying VoteContract with account:", deployer.address);

  // Ensuring the network is Sepolia
  const network = hre.network.name;
  console.log("Deploying on network:", network);
  if (network !== "sepolia") {
    throw new Error("Please run the script with --network sepolia");
  }

  // Deploying the contract
  console.log("Deploying VoteContract...");
  const VoteContract = await hre.ethers.getContractFactory("VoteContract");
  const voteContract = await VoteContract.deploy();

  // Waiting for the deployment transaction to be mined
  console.log("Waiting for deployment to be mined...");
  await voteContract.waitForDeployment(); 
  const txReceipt = await voteContract.deploymentTransaction().wait();
  console.log("Transaction receipt:", txReceipt);

  // Logging the deployed contract address
  console.log("VoteContract deployed to:", await voteContract.getAddress());
}

main().catch((error) => {
  console.error("Deployment error:", error);
  process.exitCode = 1;
});
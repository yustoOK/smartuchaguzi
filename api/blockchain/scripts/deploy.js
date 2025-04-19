const hre = require("hardhat");

async function main() {
    const [deployer] = await hre.ethers.getSigners();
    console.log("Deploying contracts with:", deployer.address);

    const ElectionContract = await hre.ethers.getContractFactory("ElectionContract");
    const electionContract = await ElectionContract.deploy();
    await electionContract.waitForDeployment();
    console.log("ElectionContract deployed to:", await electionContract.getAddress());

    const VoteContract = await hre.ethers.getContractFactory("VoteContract");
    const voteContract = await VoteContract.deploy(await electionContract.getAddress());
    await voteContract.waitForDeployment();
    console.log("VoteContract deployed to:", await voteContract.getAddress());
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
